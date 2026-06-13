<?php

declare(strict_types=1);

namespace Domain\Catalogue\Actions;

use Domain\Catalogue\Models\Lwin;
use Domain\Catalogue\Models\Product;
use Domain\Import\Services\NormaliseService;
use Domain\Shared\Actions\AbstractAction;

/**
 * Backfills filterable product columns from AUTHORITATIVE, deterministic
 * sources so the catalogue filters work — without mutating supplier-supplied
 * values (fill only where a column is empty; the supplier's own data always
 * wins). Cross-vendor wine_facts stay render-only by design.
 *
 * Three passes, in order of authority:
 *   1. LWIN reference (country/region/sub_region/colour/producer) for linked wines.
 *   2. region -> country derivation (geography is deterministic; many fine-wine
 *      lists omit country because it's implicit in the region).
 *   3. geocode lat/lng from region/country so wines appear on the sourcing map.
 *
 * Idempotent: re-running only touches still-empty columns.
 */
class BackfillCatalogueAttributesAction extends AbstractAction
{
    public function __construct(private NormaliseService $normalise = new NormaliseService) {}

    /**
     * @return array{lwin: int, country: int, geo: int}
     */
    public function execute(bool $apply = true): array
    {
        return [
            'lwin' => $this->fromLwin($apply),
            'country' => $this->countryFromRegion($apply),
            'geo' => $this->geocodeMissing($apply),
        ];
    }

    /** Pass 1 — fill empties from the matched LWIN reference row. */
    private function fromLwin(bool $apply): int
    {
        $touched = 0;

        Product::whereNull('archived_at')
            ->whereNotNull('lwin')
            ->where(fn ($q) => $q->whereNull('country')->orWhere('country', '')
                ->orWhereNull('region')->orWhere('region', '')
                ->orWhereNull('producer')->orWhere('producer', '')
                ->orWhereNull('colour'))
            ->orderBy('id')
            ->chunkById(500, function ($products) use (&$touched, $apply) {
                $lwins = Lwin::whereIn('lwin', $products->pluck('lwin')->unique())->get()->keyBy('lwin');

                foreach ($products as $product) {
                    $ref = $lwins->get($product->lwin);
                    if ($ref === null) {
                        continue;
                    }

                    $changes = [];
                    $this->fillString($changes, $product, 'country', $ref->country);
                    $this->fillString($changes, $product, 'region', $ref->region);
                    $this->fillString($changes, $product, 'sub_region', $ref->sub_region);
                    $this->fillString($changes, $product, 'producer', $ref->producer_name ?: $ref->producer_title);

                    if ($product->colour === null) {
                        $colour = $this->normalise->normaliseColour($ref->colour);
                        if ($colour !== null) {
                            $changes['colour'] = $colour->value;
                        }
                    }

                    if ($changes !== []) {
                        $touched++;
                        if ($apply) {
                            Product::whereKey($product->id)->update($changes);
                        }
                    }
                }
            });

        return $touched;
    }

    /** Pass 2 — derive country from region where country is still empty. */
    private function countryFromRegion(bool $apply): int
    {
        $touched = 0;

        Product::whereNull('archived_at')
            ->where(fn ($q) => $q->whereNull('country')->orWhere('country', ''))
            ->whereNotNull('region')->where('region', '<>', '')
            ->orderBy('id')
            ->chunkById(500, function ($products) use (&$touched, $apply) {
                foreach ($products as $product) {
                    $country = $this->regionToCountry($product->region);
                    if ($country === null) {
                        continue;
                    }

                    $touched++;
                    if ($apply) {
                        Product::whereKey($product->id)->update(['country' => $country]);
                    }
                }
            });

        return $touched;
    }

    /** Pass 3 — geocode region/country into lat/lng for the map. */
    private function geocodeMissing(bool $apply): int
    {
        $touched = 0;

        Product::whereNull('archived_at')
            ->where(fn ($q) => $q->whereNull('latitude')->orWhereNull('longitude'))
            ->where(fn ($q) => $q->whereNotNull('region')->where('region', '<>', '')
                ->orWhere(fn ($q) => $q->whereNotNull('country')->where('country', '<>', '')))
            ->orderBy('id')
            ->chunkById(500, function ($products) use (&$touched, $apply) {
                foreach ($products as $product) {
                    $coords = $this->normalise->geocode($product->region, $product->country, $product->wine_name.$product->producer);
                    if ($coords === []) {
                        continue;
                    }

                    $touched++;
                    if ($apply) {
                        Product::whereKey($product->id)->update(['latitude' => $coords['lat'], 'longitude' => $coords['lng']]);
                    }
                }
            });

        return $touched;
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function fillString(array &$changes, Product $product, string $column, ?string $value): void
    {
        $value = trim((string) $value);
        if ($value !== '' && ($product->{$column} === null || $product->{$column} === '')) {
            $changes[$column] = $value;
        }
    }

    /**
     * Deterministic region -> country. Keys are lowercased region names as they
     * appear in supplier lists; a region that IS a country name maps to itself.
     */
    private function regionToCountry(string $region): ?string
    {
        $key = strtolower(trim($region));

        return self::REGION_COUNTRY[$key]
            ?? (in_array($key, self::COUNTRIES, true) ? ucwords($key) : null);
    }

    /** Country names that suppliers sometimes put in the region field. */
    private const COUNTRIES = [
        'france', 'italy', 'spain', 'portugal', 'germany', 'austria', 'australia',
        'new zealand', 'south africa', 'chile', 'argentina', 'greece', 'hungary',
        'england', 'united states', 'usa', 'lebanon', 'georgia', 'switzerland',
    ];

    private const REGION_COUNTRY = [
        // France
        'bourgogne' => 'France', 'burgundy' => 'France', 'bordeaux' => 'France',
        'rhône' => 'France', 'rhone' => 'France', 'champagne' => 'France',
        'loire' => 'France', 'provence' => 'France', 'alsace' => 'France',
        'languedoc' => 'France', 'roussillon' => 'France', 'languedoc-roussillon' => 'France',
        'beaujolais' => 'France', 'mâconnais' => 'France', 'maconnais' => 'France',
        'côte chalonnaise' => 'France', 'cote chalonnaise' => 'France', 'chablis' => 'France',
        'sancerre' => 'France', 'jura' => 'France', 'savoie' => 'France',
        'sud-ouest' => 'France', 'south west france' => 'France', 'corsica' => 'France',
        // Italy
        'piemonte' => 'Italy', 'piedmont' => 'Italy', 'toscana' => 'Italy', 'tuscany' => 'Italy',
        'veneto' => 'Italy', 'sicilia' => 'Italy', 'sicily' => 'Italy', 'lombardia' => 'Italy',
        'friuli' => 'Italy', 'alto adige' => 'Italy', 'trentino' => 'Italy', 'puglia' => 'Italy',
        'campania' => 'Italy', 'abruzzo' => 'Italy', 'marche' => 'Italy', 'umbria' => 'Italy',
        'sardegna' => 'Italy', 'sardinia' => 'Italy', 'emilia-romagna' => 'Italy',
        // Iberia
        'rioja' => 'Spain', 'ribera del duero' => 'Spain', 'priorat' => 'Spain',
        'rías baixas' => 'Spain', 'rias baixas' => 'Spain', 'galicia' => 'Spain', 'jerez' => 'Spain',
        'penedès' => 'Spain', 'penedes' => 'Spain', 'navarra' => 'Spain', 'toro' => 'Spain', 'jumilla' => 'Spain',
        'douro' => 'Portugal', 'porto' => 'Portugal', 'dão' => 'Portugal', 'dao' => 'Portugal',
        'vinho verde' => 'Portugal', 'alentejo' => 'Portugal', 'bairrada' => 'Portugal', 'madeira' => 'Portugal',
        'douro valley' => 'Portugal',
        // Germany / Austria
        'mosel' => 'Germany', 'rheingau' => 'Germany', 'pfalz' => 'Germany', 'nahe' => 'Germany',
        'rheinhessen' => 'Germany', 'baden' => 'Germany', 'franken' => 'Germany',
        'wachau' => 'Austria', 'kamptal' => 'Austria', 'burgenland' => 'Austria', 'kremstal' => 'Austria',
        // New World
        'california' => 'United States', 'oregon' => 'United States', 'washington' => 'United States',
        'napa valley' => 'United States', 'sonoma' => 'United States', 'finger lakes' => 'United States',
        'barossa' => 'Australia', 'mclaren vale' => 'Australia', 'adelaide hills' => 'Australia',
        'margaret river' => 'Australia', 'yarra valley' => 'Australia', 'hunter valley' => 'Australia',
        'coonawarra' => 'Australia', 'clare valley' => 'Australia', 'mclaren' => 'Australia',
        'marlborough' => 'New Zealand', 'central otago' => 'New Zealand', "hawke's bay" => 'New Zealand',
        'western cape' => 'South Africa', 'stellenbosch' => 'South Africa', 'swartland' => 'South Africa',
        'franschhoek' => 'South Africa', 'walker bay' => 'South Africa',
        'aconcagua' => 'Chile', 'maipo' => 'Chile', 'colchagua' => 'Chile', 'casablanca' => 'Chile',
        'mendoza' => 'Argentina', 'salta' => 'Argentina', 'patagonia' => 'Argentina', 'uco valley' => 'Argentina',
        // Other
        'wallonie' => 'Belgium', 'tokaj' => 'Hungary',
    ];
}
