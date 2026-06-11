<?php

declare(strict_types=1);

namespace Domain\Catalogue\Services;

use Domain\Catalogue\Models\Lwin;
use Domain\Catalogue\Models\Product;
use Domain\Catalogue\Models\WineFact;
use Domain\Catalogue\Support\WineIdentity;
use Domain\Supplier\Services\ClaudeClient;
use Illuminate\Support\Facades\DB;

/**
 * Links products and wine facts to LWIN reference codes:
 *
 *   identity — producer|wine normalised key, unique LWIN matches only
 *   name     — display-name normalised key (covers producer-in-name lists
 *              like Farr's), unique matches only
 *   llm      — optional, capped: for unmatched wines whose PRODUCER matches
 *              known LWINs, a cheap model picks among that producer's wines
 *              or abstains
 *
 * Idempotent: already-matched rows are skipped. Ambiguous keys (several LWINs
 * sharing a key) are never guessed deterministically.
 */
class LwinMatchService
{
    public function __construct(private ClaudeClient $claude = new ClaudeClient) {}

    /**
     * @return array{products: array<string, int>, facts: array<string, int>}
     */
    public function match(bool $withLlm = false, int $llmLimit = 500, ?string $model = null): array
    {
        $identityMap = $this->uniqueKeyMap('identity_key');
        $nameMap = $this->uniqueKeyMap('name_key');

        $stats = ['products' => ['identity' => 0, 'name' => 0, 'llm' => 0, 'unmatched' => 0],
            'facts' => ['identity' => 0, 'name' => 0, 'llm' => 0, 'unmatched' => 0]];

        Product::whereNull('lwin')->chunkById(500, function ($products) use ($identityMap, $nameMap, &$stats) {
            foreach ($products as $product) {
                $source = $this->deterministic($product->producer, $product->wine_name, $identityMap, $nameMap, $lwin);

                if ($source !== null) {
                    $product->update(['lwin' => $lwin, 'lwin_source' => $source]);
                    $stats['products'][$source]++;
                } else {
                    $stats['products']['unmatched']++;
                }
            }
        });

        WineFact::whereNull('lwin')->chunkById(500, function ($facts) use ($identityMap, $nameMap, &$stats) {
            foreach ($facts as $fact) {
                $source = $this->deterministic($fact->producer, $fact->wine_name, $identityMap, $nameMap, $lwin);

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

        return $stats;
    }

    /**
     * @param  array<string, string>  $identityMap
     * @param  array<string, string>  $nameMap
     */
    private function deterministic(?string $producer, ?string $wineName, array $identityMap, array $nameMap, ?string &$lwin): ?string
    {
        $lwin = null;

        $identity = WineIdentity::keyFor($producer, $wineName);
        if ($identity !== null && isset($identityMap[$identity])) {
            $lwin = $identityMap[$identity];

            return 'identity';
        }

        // Display-name pass: the wine name alone, and producer+name combined,
        // against LWIN display names (handles producer-embedded names).
        foreach ([WineIdentity::normalise($wineName), WineIdentity::normalise(trim(($producer ?? '').' '.$wineName))] as $key) {
            if ($key !== '' && isset($nameMap[$key])) {
                $lwin = $nameMap[$key];

                return 'name';
            }
        }

        return null;
    }

    /**
     * Keys that map to exactly ONE LWIN (ambiguous keys are never guessed).
     *
     * @return array<string, string>
     */
    private function uniqueKeyMap(string $column): array
    {
        return Lwin::whereNotNull($column)
            ->select($column, DB::raw('MIN(lwin) as lwin'), DB::raw('COUNT(*) as c'))
            ->groupBy($column)
            ->having('c', 1)
            ->pluck('lwin', $column)
            ->all();
    }

    /**
     * Model-assisted residue: products whose producer exists in LWIN but whose
     * wine name didn't match — a cheap model picks among that producer's
     * wines or abstains. Capped; batched.
     */
    private function llmPass(int $limit, ?string $model): int
    {
        $matched = 0;
        $batch = [];

        $products = Product::whereNull('lwin')
            ->whereNotNull('producer')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($products as $product) {
            $producerKey = WineIdentity::normalise($product->producer);

            if ($producerKey === '') {
                continue;
            }

            $candidates = Lwin::where('identity_key', 'like', str_replace(['%', '_'], ['\%', '\_'], $producerKey).'|%')
                ->limit(8)
                ->get(['lwin', 'display_name'])
                ->map(fn (Lwin $l) => ['lwin' => $l->lwin, 'name' => (string) $l->display_name])
                ->all();

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
     * @param  array<int, array{product: Product, candidates: array<int, array{lwin: string, name: string}>}>  $batch
     */
    private function resolveBatch(array $batch, ?string $model): int
    {
        if ($batch === []) {
            return 0;
        }

        $items = array_map(fn (array $entry, int $i) => [
            'index' => (string) $i,
            'wine' => trim(($entry['product']->producer ?? '').' — '.$entry['product']->wine_name),
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
