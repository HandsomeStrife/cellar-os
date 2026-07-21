<?php

declare(strict_types=1);

namespace Domain\Catalogue\Actions;

use Domain\Catalogue\Models\Product;
use Domain\Import\Services\NormaliseService;
use Domain\Shared\Actions\AbstractAction;
use Illuminate\Support\Carbon;

/**
 * One-off (idempotent) repair of geography columns that make the catalogue
 * filters confusing:
 *
 *   1. region == country — some importers copy the COUNTRY into the region
 *      column when they can't identify a finer region, so country names
 *      ("Italy", "Germany") turn up in the region filter. Where the real
 *      region sits one column down (Farr: region=Italy, sub_region=Toscana)
 *      we promote it; otherwise we clear the region (the country still stands).
 *      Only STRICT sovereign/home-nation names are treated this way, so genuine
 *      regions that merely share a name with a country (South Australia,
 *      Burgundy) are left untouched.
 *   2. junk country strings — Les Caves' classified list bled scrambled text
 *      and trade annotations ("Italy - NFD", "Spain   SHERRY - CLASSIFIED
 *      LIST£…") into the country column; NormaliseService::normaliseCountry
 *      recovers the clean country. Genuine non-wine section headers left as
 *      rows (KEG/KEYKEGS…) are archived.
 *   3. country synonyms — folds "United States"/"USA", tidies SHOUT-CASE, and
 *      moves macro-regions parked in the country column ("South-West France")
 *      into the region column with the parent country.
 *
 * All changes ride golden, so prod mirrors the cleanup. Re-running is a no-op.
 */
class CleanCatalogueGeographyAction extends AbstractAction
{
    public function __construct(private NormaliseService $normalise = new NormaliseService) {}

    /**
     * @return array{archived: int, region_demoted: int, region_cleared: int, country_filled: int, region_recovered: int, country_canonicalised: int, sub_region_dedup: int}
     */
    public function execute(bool $apply = true): array
    {
        $archived = $this->archiveNonWineHeaders($apply);
        $region = $this->repairRegionEqualsCountry($apply);
        $country = $this->canonicaliseCountries($apply);
        $subDedup = $this->clearDuplicateSubRegion($apply);

        return [
            'archived' => $archived,
            'region_demoted' => $region['demoted'],
            'region_cleared' => $region['cleared'],
            'country_filled' => $region['country_filled'],
            'region_recovered' => $country['recovered'],
            'country_canonicalised' => $country['canonicalised'],
            'sub_region_dedup' => $subDedup,
        ];
    }

    /**
     * Pass 4 — clear a sub_region that merely duplicates its region (it adds
     * nothing and clutters the sub-region filter). This happens when a region
     * was promoted out of sub_region on one environment but a golden import on
     * another couldn't blank the now-redundant sub_region (null incoming values
     * are never applied over an existing value).
     */
    private function clearDuplicateSubRegion(bool $apply): int
    {
        $query = Product::whereNull('archived_at')
            ->whereNotNull('sub_region')->where('sub_region', '<>', '')
            ->whereColumn('region', 'sub_region');

        $count = $query->count();

        if ($count > 0 && $apply) {
            (clone $query)->update(['sub_region' => null]);
        }

        return $count;
    }

    /**
     * Pass 1 — archive rows whose wine_name is nothing but a section header
     * (a classified-list style/format tag captured as if it were a wine).
     */
    private function archiveNonWineHeaders(bool $apply): int
    {
        $query = Product::whereNull('archived_at')
            ->whereIn('wine_name', self::SECTION_HEADERS);

        $count = $query->count();

        if ($count > 0 && $apply) {
            (clone $query)->update(['archived_at' => Carbon::now()]);
        }

        return $count;
    }

    /**
     * Pass 2 — where region holds a bare country name: promote sub_region into
     * region when present, else clear region; fill an empty country from it.
     *
     * @return array{demoted: int, cleared: int, country_filled: int}
     */
    private function repairRegionEqualsCountry(bool $apply): array
    {
        $demoted = $cleared = $countryFilled = 0;

        Product::whereNull('archived_at')
            ->whereNotNull('region')->where('region', '<>', '')
            ->orderBy('id')
            ->chunkById(500, function ($products) use (&$demoted, &$cleared, &$countryFilled, $apply) {
                foreach ($products as $product) {
                    if (! in_array(mb_strtolower(trim((string) $product->region)), self::STRICT_COUNTRIES, true)) {
                        continue;
                    }

                    $changes = [];

                    // Fill an empty country from the country-in-region value.
                    if ($product->country === null || trim((string) $product->country) === '') {
                        $changes['country'] = $this->normalise->normaliseCountry($product->region);
                        $countryFilled++;
                    }

                    // Promote a real region parked in sub_region, else clear.
                    $sub = trim((string) $product->sub_region);
                    if ($sub !== '') {
                        $changes['region'] = $product->sub_region;
                        $changes['sub_region'] = null;
                        $demoted++;
                    } else {
                        $changes['region'] = null;
                        $cleared++;
                    }

                    if ($apply) {
                        Product::whereKey($product->id)->update($changes);
                    }
                }
            });

        return ['demoted' => $demoted, 'cleared' => $cleared, 'country_filled' => $countryFilled];
    }

    /**
     * Pass 3 — recover macro-regions parked in the country column, then
     * canonicalise every remaining country string.
     *
     * @return array{recovered: int, canonicalised: int}
     */
    private function canonicaliseCountries(bool $apply): array
    {
        $recovered = $canonicalised = 0;

        Product::whereNull('archived_at')
            ->whereNotNull('country')->where('country', '<>', '')
            ->orderBy('id')
            ->chunkById(500, function ($products) use (&$recovered, &$canonicalised, $apply) {
                foreach ($products as $product) {
                    $changes = [];
                    $rawKey = mb_strtolower(trim((string) $product->country));
                    $leak = self::COUNTRY_IS_REGION[$rawKey] ?? null;

                    if ($leak !== null && ($product->region === null || trim((string) $product->region) === '')) {
                        [$changes['country'], $changes['region']] = $leak;
                        $recovered++;
                    } else {
                        $canon = $this->normalise->normaliseCountry($product->country);
                        if ($canon !== $product->country) {
                            $changes['country'] = $canon;
                            $canonicalised++;
                        }
                    }

                    if ($changes !== [] && $apply) {
                        Product::whereKey($product->id)->update($changes);
                    }
                }
            });

        return ['recovered' => $recovered, 'canonicalised' => $canonicalised];
    }

    /** Wine-name values that are really classified-list section headers. */
    private const SECTION_HEADERS = [
        'RED WINES', 'WHITE WINES', 'SPARKLING WINES', 'ROSÉ WINES', 'ROSE WINES',
        'ORANGE/SKIN CONTACT', 'ORANGE/SKIN CONTACT WINES', 'SWEET WINES', 'SHERRY',
        'VERMOUTH', 'OTHER FORTIFIED WINES', 'MAGNUMS', 'HALF BOTTLES', 'BAG-IN-BOX',
        'KEG/KEYKEGS', 'KEYKEGS', 'POLYKEGS', 'CIDERS/PERRIES', 'SAKE', 'CLASSIFIED',
    ];

    /**
     * Macro-regions parked in the country column → [parent country, region label].
     * Mirrors NormaliseService for the one-time repair of already-stored rows.
     */
    private const COUNTRY_IS_REGION = [
        'south-west france' => ['France', 'South-West France'],
        'south west france' => ['France', 'South-West France'],
        'loire france' => ['France', 'Loire'],
        'burgundy' => ['France', 'Bourgogne'],
        'north-east spain' => ['Spain', 'North-East Spain'],
    ];

    /**
     * STRICT country names (lowercased) that must never sit in the region
     * column. Sovereign states plus the UK home nations (which CellarOS treats
     * as countries). Deliberately excludes sub-national regions that share a
     * name with nothing here (South Australia, Burgundy) so they stay as regions.
     */
    private const STRICT_COUNTRIES = [
        'france', 'italy', 'spain', 'portugal', 'germany', 'austria', 'australia',
        'new zealand', 'south africa', 'chile', 'argentina', 'greece', 'hungary',
        'england', 'wales', 'scotland', 'usa', 'united states', 'united states of america',
        'lebanon', 'georgia', 'armenia', 'switzerland', 'romania', 'slovenia', 'slovakia',
        'croatia', 'moldova', 'north macedonia', 'macedonia', 'israel', 'mexico', 'ukraine',
        'bulgaria', 'cyprus', 'denmark', 'uruguay', 'india', 'japan', 'canada', 'belgium',
        'morocco', 'serbia', 'czechia', 'united kingdom',
    ];
}
