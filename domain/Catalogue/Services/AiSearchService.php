<?php

declare(strict_types=1);

namespace Domain\Catalogue\Services;

use Domain\Catalogue\Data\AiSearchFilterData;
use Domain\Catalogue\Data\ProductData;
use Domain\Supplier\Services\ClaudeClient;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Plain-English catalogue search.
 *
 * The model's only job is to read the buyer's sentence into the filters the
 * catalogue already has — it never writes a query and never sees another
 * tenant's data. The filter it returns is run through the same scoped
 * ProductRepository::search() a hand-set filter uses, so there is no path by
 * which a bad interpretation could widen the buyer's scope; the worst it can
 * do is pick the wrong region.
 *
 * "Why this wine?" is answered deterministically, from which of the applied
 * criteria each wine actually satisfies, rather than by asking the model to
 * justify itself. That costs nothing, never contradicts the results, and
 * cannot invent a reason a wine doesn't meet.
 */
class AiSearchService
{
    /** Interpretations are stable for a given question, so cache them. */
    private const CACHE_TTL_MINUTES = 60;

    public function __construct(private ClaudeClient $claude = new ClaudeClient) {}

    /**
     * @param  array<string, array<int, string>>  $facets  values present in this buyer's
     *                                                     catalogue, so the model picks real ones
     */
    public function interpret(string $query, array $facets, ?int $companyId = null): ?AiSearchFilterData
    {
        $query = trim($query);

        if ($query === '') {
            return null;
        }

        // Keyed on the company too: two buyers asking the same question have
        // different catalogues, so the sensible interpretation differs.
        $key = 'ai-search:'.$companyId.':'.sha1(mb_strtolower($query));

        return Cache::remember($key, now()->addMinutes(self::CACHE_TTL_MINUTES), function () use ($query, $facets) {
            try {
                return AiSearchFilterData::fromModelOutput(
                    $this->claude->interpretSearch($query, $facets)
                );
            } catch (Throwable) {
                // A search that can't reach the model should fall back to an
                // ordinary text search, not present the buyer with an error.
                return null;
            }
        });
    }

    /**
     * Order in which criteria are given up when a reading returns nothing.
     *
     * Sparsest and least load-bearing first. Grape leads because supplier lists
     * record it so unevenly — on the working catalogue only 40 of 4,950
     * Bourgogne wines carry one, so a technically-correct "Chardonnay" filter
     * hides 1,500 white Burgundies. Type and price go last: those are what a
     * buyer actually meant.
     *
     * @var array<int, string>
     */
    private const RELAX_ORDER = ['grape', 'producer', 'sub_type', 'region', 'vintage', 'search', 'country'];

    /**
     * Give up criteria, one at a time, until the search finds something.
     *
     * A buyer asking for white Burgundy Chardonnay wants white Burgundy, not an
     * empty page — but they must be told what was set aside, so this reports
     * every criterion it dropped rather than quietly widening the search.
     *
     * @param  callable(AiSearchFilterData): int  $count  how many wines this filter finds
     * @return array{filter: AiSearchFilterData, dropped: array<int, string>}
     */
    public function relax(AiSearchFilterData $filter, callable $count): array
    {
        $dropped = [];

        if ($count($filter) > 0) {
            return ['filter' => $filter, 'dropped' => $dropped];
        }

        foreach (self::RELAX_ORDER as $criterion) {
            $without = $this->without($filter, $criterion);

            if ($without === null) {
                continue; // wasn't set anyway
            }

            $dropped[] = $criterion === 'vintage' ? 'vintage' : $criterion;
            $filter = $without;

            if ($count($filter) > 0) {
                break;
            }
        }

        return ['filter' => $filter, 'dropped' => $dropped];
    }

    /**
     * A copy of the filter without one criterion, or null if it wasn't set.
     */
    private function without(AiSearchFilterData $filter, string $criterion): ?AiSearchFilterData
    {
        $clone = clone $filter;

        $wasSet = match ($criterion) {
            'grape' => $filter->grape !== '',
            'producer' => $filter->producer !== '',
            'sub_type' => $filter->sub_type !== null,
            'region' => $filter->region !== '',
            'vintage' => $filter->vintage_min !== null || $filter->vintage_max !== null,
            'search' => $filter->search !== '',
            'country' => $filter->country !== '',
            default => false,
        };

        if (! $wasSet) {
            return null;
        }

        match ($criterion) {
            'grape' => $clone->grape = '',
            'producer' => $clone->producer = '',
            'sub_type' => $clone->sub_type = null,
            'region' => $clone->region = '',
            'vintage' => [$clone->vintage_min, $clone->vintage_max] = [null, null],
            'search' => $clone->search = '',
            'country' => $clone->country = '',
            default => null,
        };

        return $clone;
    }

    /**
     * Why each wine is in the results: the criteria it actually meets.
     *
     * @param  array<int, ProductData>  $products
     * @return array<int, string> product id => reason
     */
    public function reasons(AiSearchFilterData $filter, array $products): array
    {
        $reasons = [];

        foreach ($products as $product) {
            $met = [];

            if ($filter->type !== null && $product->colour === $filter->type) {
                $met[] = $filter->type->getLabel();
            }
            if ($filter->sub_type !== null && $product->sub_type === $filter->sub_type) {
                $met[] = $filter->sub_type->getShortLabel();
            }
            if ($filter->country !== '' && $product->country === $filter->country) {
                $met[] = $product->country;
            }
            if ($filter->region !== '' && $product->region === $filter->region) {
                $met[] = $product->region;
            }
            if ($filter->grape !== '' && $product->grape !== null && $product->grape !== []) {
                $met[] = implode(', ', $product->grape);
            }
            if ($filter->producer !== '' && ($product->producer ?? '') !== '') {
                $met[] = $product->producer;
            }
            if (($filter->price_min !== null || $filter->price_max !== null) && $product->unit_price !== null) {
                $met[] = 'within budget at '.number_format((float) $product->unit_price, 2).' a bottle';
            }
            if (($filter->vintage_min !== null || $filter->vintage_max !== null) && $product->vintage !== null) {
                $met[] = (string) $product->vintage;
            }
            if ($filter->search !== '') {
                $met[] = 'matches "'.$filter->search.'"';
            }

            if ($met !== []) {
                $reasons[$product->id] = ucfirst(implode(' · ', $met));
            }
        }

        return $reasons;
    }
}
