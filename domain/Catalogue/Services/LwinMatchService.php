<?php

declare(strict_types=1);

namespace Domain\Catalogue\Services;

use Domain\Catalogue\Models\Lwin;
use Domain\Catalogue\Models\Product;
use Domain\Catalogue\Models\WineFact;
use Domain\Catalogue\Support\WineIdentity;
use Domain\Supplier\Services\ClaudeClient;

/**
 * Links products and wine facts to LWIN reference codes.
 *
 * Real lists and LWIN name things differently, so both sides generate VARIANT
 * keys before comparing — producer titles (Chateau/Domaine/…) made optional,
 * ", Producer" suffixes stripped from wine names (Flint-style), appellation
 * comma-segments stripped from LWIN display names — and a key is only ever
 * accepted when it maps to exactly ONE LWIN across the whole expanded key
 * set. Ambiguity is never guessed deterministically; the optional capped LLM
 * pass shortlists candidates by producer/rare-token and lets a cheap model
 * pick or abstain (picks outside the shortlist are discarded).
 */
class LwinMatchService
{
    private const TITLES = ['chateau', 'domaine', 'dom', 'maison', 'bodega', 'bodegas', 'weingut', 'tenuta', 'azienda agricola', 'cantina', 'quinta', 'champagne'];

    private const STOPWORDS = ['chateau', 'domaine', 'the', 'les', 'la', 'le', 'de', 'des', 'du', 'di', 'grand', 'cru', 'premier', '1er', 'vineyard', 'vineyards', 'estate', 'reserve', 'riserva', 'blanc', 'rouge', 'red', 'white', 'wine', 'and', 'saint', 'st'];

    public function __construct(private ClaudeClient $claude = new ClaudeClient) {}

    /**
     * @return array{products: array<string, int>, facts: array<string, int>}
     */
    public function match(bool $withLlm = false, int $llmLimit = 500, ?string $model = null): array
    {
        $map = $this->referenceMap();

        $stats = ['products' => ['identity' => 0, 'name' => 0, 'llm' => 0, 'unmatched' => 0],
            'facts' => ['identity' => 0, 'name' => 0, 'llm' => 0, 'unmatched' => 0]];

        Product::whereNull('lwin')->chunkById(500, function ($products) use ($map, &$stats) {
            foreach ($products as $product) {
                $source = $this->deterministic($product->producer, $product->wine_name, $map, $lwin);

                if ($source !== null) {
                    $product->update(['lwin' => $lwin, 'lwin_source' => $source]);
                    $stats['products'][$source]++;
                } else {
                    $stats['products']['unmatched']++;
                }
            }
        });

        WineFact::whereNull('lwin')->chunkById(500, function ($facts) use ($map, &$stats) {
            foreach ($facts as $fact) {
                $source = $this->deterministic($fact->producer, $fact->wine_name, $map, $lwin);

                if ($source !== null) {
                    $fact->update(['lwin' => $lwin, 'lwin_source' => $source]);
                    $stats['facts'][$source]++;
                } else {
                    $stats['facts']['unmatched']++;
                }
            }
        });

        if ($withLlm) {
            $stats['products']['llm'] = $this->llmPass($llmLimit, $model);
            $stats['products']['unmatched'] -= $stats['products']['llm'];
        }

        // Facts inherit LWINs from matched products sharing their identity
        // key (conflicting product links are skipped, never guessed).
        $propagated = $this->propagateToFacts();
        $stats['facts']['product'] = $propagated;
        $stats['facts']['unmatched'] = max(0, $stats['facts']['unmatched'] - $propagated);

        return $stats;
    }

    private function propagateToFacts(): int
    {
        $byIdentity = [];

        Product::whereNotNull('lwin')->chunkById(500, function ($products) use (&$byIdentity) {
            foreach ($products as $product) {
                $key = WineIdentity::keyFor($product->producer, $product->wine_name);
                if ($key === null) {
                    continue;
                }
                if (array_key_exists($key, $byIdentity) && $byIdentity[$key] !== $product->lwin) {
                    $byIdentity[$key] = null; // conflicting links — never guess
                } elseif (! array_key_exists($key, $byIdentity)) {
                    $byIdentity[$key] = $product->lwin;
                }
            }
        });

        $count = 0;

        WineFact::whereNull('lwin')->chunkById(500, function ($facts) use ($byIdentity, &$count) {
            foreach ($facts as $fact) {
                $lwin = $byIdentity[$fact->identity_key] ?? null;
                if ($lwin !== null) {
                    $fact->update(['lwin' => $lwin, 'lwin_source' => 'product']);
                    $count++;
                }
            }
        });

        return $count;
    }

    /**
     * Expanded LWIN key set: key => lwin for keys unique across the whole set;
     * ambiguous keys are recorded as null and never matched.
     *
     * @return array{identity: array<string, string|null>, name: array<string, string|null>}
     */
    private function referenceMap(): array
    {
        $identity = [];
        $name = [];

        $add = function (array &$bucket, ?string $key, string $lwin): void {
            if ($key === null || $key === '') {
                return;
            }
            // First writer wins; a second DIFFERENT lwin poisons the key.
            if (array_key_exists($key, $bucket) && $bucket[$key] !== $lwin) {
                $bucket[$key] = null;
            } elseif (! array_key_exists($key, $bucket)) {
                $bucket[$key] = $lwin;
            }
        };

        Lwin::query()->select(['id', 'lwin', 'display_name', 'producer_title', 'producer_name', 'wine'])
            ->chunkById(5000, function ($rows) use (&$identity, &$name, $add) {
                foreach ($rows as $row) {
                    $pn = WineIdentity::normalise($row->producer_name);
                    $ptpn = WineIdentity::normalise(trim(($row->producer_title ?? '').' '.($row->producer_name ?? '')));
                    $wine = WineIdentity::normalise($row->wine);
                    $display = WineIdentity::normalise($row->display_name);

                    foreach (array_unique([$pn, $ptpn]) as $producerKey) {
                        if ($producerKey !== '' && $wine !== '') {
                            $add($identity, $producerKey.'|'.$wine, $row->lwin);
                        }
                    }

                    $displayVariants = [$display];
                    // Strip the trailing appellation segment ("…, Pauillac").
                    if (str_contains((string) $row->display_name, ',')) {
                        $displayVariants[] = WineIdentity::normalise(implode(',', array_slice(explode(',', (string) $row->display_name), 0, -1)));
                    }
                    // Producer + wine composites.
                    $displayVariants[] = $ptpn !== '' && $wine !== '' ? $ptpn.' '.$wine : '';
                    $displayVariants[] = $pn !== '' && $wine !== '' ? $pn.' '.$wine : '';

                    foreach (array_unique(array_filter($displayVariants)) as $variant) {
                        $add($name, $variant, $row->lwin);
                    }
                }
            }, column: 'id');

        return ['identity' => $identity, 'name' => $name];
    }

    /**
     * @param  array{identity: array<string, string|null>, name: array<string, string|null>}  $map
     */
    private function deterministic(?string $producer, ?string $wineName, array $map, ?string &$lwin): ?string
    {
        $lwin = null;

        foreach ($this->producerVariants($producer) as $producerKey) {
            foreach ($this->wineVariants($wineName, $producer) as $wineKey) {
                $hit = $map['identity'][$producerKey.'|'.$wineKey] ?? null;
                if ($hit !== null) {
                    $lwin = $hit;

                    return 'identity';
                }
            }
        }

        $nameTries = [];
        foreach ($this->wineVariants($wineName, $producer) as $wineKey) {
            $nameTries[] = $wineKey;
            foreach ($this->producerVariants($producer) as $producerKey) {
                $nameTries[] = $producerKey.' '.$wineKey;
            }
        }

        foreach (array_unique($nameTries) as $key) {
            $hit = $map['name'][$key] ?? null;
            if ($hit !== null) {
                $lwin = $hit;

                return 'name';
            }
        }

        return null;
    }

    /**
     * Folded producer variants: as-is, and with a leading title stripped.
     *
     * @return array<int, string>
     */
    private function producerVariants(?string $producer): array
    {
        $folded = WineIdentity::normalise($producer);

        if ($folded === '') {
            return [];
        }

        $variants = [$folded];

        foreach (self::TITLES as $title) {
            if (str_starts_with($folded, $title.' ')) {
                $variants[] = trim(substr($folded, strlen($title) + 1));
                break;
            }
        }

        return array_values(array_unique(array_filter($variants)));
    }

    /**
     * Folded wine-name variants: as-is, and with a trailing ", Producer"
     * segment stripped when it repeats the producer (Flint-style names).
     *
     * @return array<int, string>
     */
    private function wineVariants(?string $wineName, ?string $producer): array
    {
        $raw = trim((string) $wineName);

        if ($raw === '') {
            return [];
        }

        $variants = [WineIdentity::normalise($raw)];

        if (str_contains($raw, ',')) {
            $segments = explode(',', $raw);
            $last = WineIdentity::normalise((string) end($segments));
            $producerFold = WineIdentity::normalise($producer);

            // Strip a trailing segment that restates the producer (loosely).
            if ($last !== '' && ($producerFold === '' || str_contains($producerFold, $last) || str_contains($last, $producerFold) || similar_text($last, $producerFold) > strlen($last) * 0.6)) {
                $variants[] = WineIdentity::normalise(implode(',', array_slice($segments, 0, -1)));
            }
        }

        return array_values(array_unique(array_filter($variants)));
    }

    /**
     * Model-assisted residue, capped: candidates shortlisted by producer match
     * or by the wine name's rarest token; the model picks or abstains.
     */
    private function llmPass(int $limit, ?string $model): int
    {
        $matched = 0;
        $batch = [];

        $products = Product::whereNull('lwin')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($products as $product) {
            $candidates = $this->candidatesFor($product->producer, $product->wine_name);

            if ($candidates === []) {
                continue;
            }

            $batch[] = ['product' => $product, 'candidates' => $candidates];

            if (count($batch) >= 25) {
                $matched += $this->resolveBatch($batch, $model);
                $batch = [];
            }
        }

        $matched += $this->resolveBatch($batch, $model);

        return $matched;
    }

    /**
     * @return array<int, array{lwin: string, name: string}>
     */
    private function candidatesFor(?string $producer, ?string $wineName): array
    {
        $candidates = collect();

        // Producer-anchored candidates.
        foreach ($this->producerVariants($producer) as $producerKey) {
            $escaped = str_replace(['%', '_'], ['\%', '\_'], $producerKey);
            $candidates = $candidates->merge(
                Lwin::where('identity_key', 'like', $escaped.'|%')
                    ->orWhere('name_key', 'like', $escaped.' %')
                    ->limit(6)
                    ->get(['lwin', 'display_name'])
            );

            if ($candidates->isNotEmpty()) {
                break;
            }
        }

        // Rare-token candidates from the wine name.
        if ($candidates->count() < 6) {
            $token = $this->rarestToken($wineName);

            if ($token !== null) {
                $escaped = str_replace(['%', '_'], ['\%', '\_'], $token);
                $candidates = $candidates->merge(
                    Lwin::where('name_key', 'like', '%'.$escaped.'%')
                        ->limit(6)
                        ->get(['lwin', 'display_name'])
                );
            }
        }

        return $candidates->unique('lwin')
            ->take(8)
            ->map(fn ($l) => ['lwin' => $l->lwin, 'name' => (string) $l->display_name])
            ->values()
            ->all();
    }

    private function rarestToken(?string $wineName): ?string
    {
        $tokens = array_filter(
            explode(' ', WineIdentity::normalise($wineName)),
            fn ($t) => mb_strlen($t) >= 5 && ! in_array($t, self::STOPWORDS, true) && ! preg_match('/^\d+$/', $t),
        );

        if ($tokens === []) {
            return null;
        }

        usort($tokens, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        return $tokens[0];
    }

    /**
     * @param  array<int, array{product: Product, candidates: array<int, array{lwin: string, name: string}>}>  $batch
     */
    private function resolveBatch(array $batch, ?string $model): int
    {
        if ($batch === []) {
            return 0;
        }

        $items = array_map(fn (array $entry, int $i) => [
            'index' => (string) $i,
            'wine' => trim(($entry['product']->producer ? $entry['product']->producer.' — ' : '').$entry['product']->wine_name),
            'candidates' => $entry['candidates'],
        ], $batch, array_keys($batch));

        $picks = $this->claude->pickLwins($items, $model);
        $matched = 0;

        foreach ($picks as $index => $lwin) {
            $entry = $batch[(int) $index] ?? null;
            $valid = $entry !== null && in_array($lwin, array_column($entry['candidates'], 'lwin'), true);

            if ($valid) {
                $entry['product']->update(['lwin' => $lwin, 'lwin_source' => 'llm']);
                $matched++;
            }
        }

        return $matched;
    }
}
