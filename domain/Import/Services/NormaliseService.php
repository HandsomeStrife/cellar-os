<?php

declare(strict_types=1);

namespace Domain\Import\Services;

use Domain\Catalogue\Data\ProductData;
use Domain\Catalogue\Enums\WineColour;

/**
 * Turns a raw uploaded price-list row into a normalised ProductData using a
 * column mapping (productField => source header). Mirrors the upstream
 * normalise.ts: colour mapping, grape splitting, price/vintage/format parsing.
 */
class NormaliseService
{
    /**
     * @param  array<string, mixed>  $row  source header => value
     * @param  array<string, string>  $mapping  productField => source header
     */
    public function toProductData(array $row, array $mapping, ?int $supplierId = null, ?int $rawUploadId = null): ?ProductData
    {
        $value = fn (string $field) => isset($mapping[$field], $row[$mapping[$field]])
            ? trim((string) $row[$mapping[$field]])
            : null;

        $wineName = $value('wine_name');

        if ($wineName === null || $wineName === '') {
            return null;
        }

        $unitPrice = $this->parsePrice($value('unit_price'));
        $formatMl = $this->parseFormatMl($value('format_ml'));
        $country = $this->nullableString($value('country'));
        $region = $this->standardiseRegion($value('region'));
        $producer = $this->nullableString($value('producer'));

        $pricePerLitre = $unitPrice !== null && $formatMl > 0
            ? round($unitPrice / ($formatMl / 1000), 2)
            : null;

        // Geocode from region/country so the wine appears on the sourcing map.
        $coords = $this->geocode($region, $country, $wineName.$producer);

        return new ProductData(
            id: null,
            uuid: null,
            supplier_id: $supplierId,
            raw_upload_id: $rawUploadId,
            wine_name: $wineName,
            producer: $producer,
            country: $country,
            region: $region,
            sub_region: $this->nullableString($value('sub_region')),
            grape: $this->parseGrapes($value('grape')),
            colour: $this->normaliseColour($value('colour')),
            vintage: $this->parseVintage($value('vintage')),
            format_ml: $formatMl,
            case_size: $this->parseInt($value('case_size')) ?? 6,
            unit_price: $unitPrice !== null ? number_format($unitPrice, 2, '.', '') : null,
            price_per_litre: $pricePerLitre !== null ? number_format($pricePerLitre, 2, '.', '') : null,
            stock: $this->parseInt($value('stock')) ?? 0,
            latitude: $coords['lat'] ?? null,
            longitude: $coords['lng'] ?? null,
        );
    }

    /** Region aliases → canonical name. */
    private const REGION_ALIASES = [
        'burgundy' => 'Bourgogne',
        'rhone' => 'Rhône',
        'tuscany' => 'Toscana',
        'piedmont' => 'Piemonte',
        'sicily' => 'Sicilia',
        'douro valley' => 'Douro',
    ];

    /** Grape aliases (lowercased) → canonical name. */
    private const GRAPE_ALIASES = [
        'cab sauv' => 'Cabernet Sauvignon', 'cab sav' => 'Cabernet Sauvignon', 'cabernet' => 'Cabernet Sauvignon',
        'cab franc' => 'Cabernet Franc', 'shiraz' => 'Syrah', 'grenache' => 'Grenache', 'garnacha' => 'Grenache',
        'chard' => 'Chardonnay', 'sauv blanc' => 'Sauvignon Blanc', 'sb' => 'Sauvignon Blanc',
        'pinot grigio' => 'Pinot Grigio', 'pinot gris' => 'Pinot Gris', 'gewurz' => 'Gewürztraminer',
        'zin' => 'Zinfandel', 'monastrell' => 'Mourvèdre', 'mourvedre' => 'Mourvèdre',
        'prosecco' => 'Glera', 'glera' => 'Glera', 'chenin' => 'Chenin Blanc',
    ];

    /** Region (lowercased canonical/alias) → [lat, lng]. */
    private const REGION_COORDS = [
        'bourgogne' => [47.0, 4.8], 'bordeaux' => [44.84, -0.58], 'champagne' => [49.04, 4.02],
        'loire' => [47.33, 0.69], 'rhône' => [44.9, 4.8], 'provence' => [43.5, 6.0],
        'piemonte' => [44.7, 8.0], 'toscana' => [43.4, 11.3], 'rioja' => [42.46, -2.45],
        'douro' => [41.16, -7.79], 'mosel' => [49.98, 7.11], 'napa valley' => [38.5, -122.27],
        'marlborough' => [-41.5, 173.86], 'barossa' => [-34.5, 138.9],
    ];

    /** Country → [lat, lng]. */
    private const COUNTRY_COORDS = [
        'france' => [46.6, 2.4], 'italy' => [41.9, 12.6], 'spain' => [40.4, -3.7],
        'portugal' => [39.4, -8.2], 'germany' => [51.2, 10.4], 'united states' => [39.8, -98.6],
        'usa' => [39.8, -98.6], 'new zealand' => [-41.0, 174.0], 'australia' => [-25.3, 133.8],
        'argentina' => [-38.4, -63.6], 'chile' => [-35.7, -71.5], 'south africa' => [-30.6, 22.9],
        'united kingdom' => [54.0, -2.0],
    ];

    public function standardiseRegion(?string $value): ?string
    {
        $value = $this->nullableString($value);

        if ($value === null) {
            return null;
        }

        return self::REGION_ALIASES[strtolower($value)] ?? ucwords($value);
    }

    private function standardiseGrape(string $grape): string
    {
        return self::GRAPE_ALIASES[strtolower($grape)] ?? ucwords($grape);
    }

    /**
     * @return array{lat: string, lng: string}|array{}
     */
    public function geocode(?string $region, ?string $country, string $seed): array
    {
        $coords = ($region !== null ? (self::REGION_COORDS[strtolower($region)] ?? null) : null)
            ?? ($country !== null ? (self::COUNTRY_COORDS[strtolower($country)] ?? null) : null);

        if ($coords === null) {
            return [];
        }

        // Deterministic ±0.1° jitter so co-located wines don't stack on the map.
        $jitter = fn (int $salt) => ((crc32($seed.$salt) % 200) - 100) / 1000;

        return [
            'lat' => number_format($coords[0] + $jitter(1), 6, '.', ''),
            'lng' => number_format($coords[1] + $jitter(2), 6, '.', ''),
        ];
    }

    public function normaliseColour(?string $value): ?WineColour
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = strtolower(trim($value));

        return match (true) {
            str_contains($value, 'sparkl'), str_contains($value, 'champagne'), str_contains($value, 'spumante'), str_contains($value, 'cava'), str_contains($value, 'cremant'), str_contains($value, 'prosecco') => WineColour::Sparkling,
            str_contains($value, 'rosé'), str_contains($value, 'rose'), str_contains($value, 'rosado'), str_contains($value, 'rosato') => WineColour::Rose,
            str_contains($value, 'orange') => WineColour::Orange,
            str_contains($value, 'dessert'), str_contains($value, 'sweet'), str_contains($value, 'sauternes') => WineColour::Dessert,
            str_contains($value, 'fortif'), str_contains($value, 'port'), str_contains($value, 'sherry'), str_contains($value, 'madeira'), str_contains($value, 'marsala') => WineColour::Fortified,
            str_contains($value, 'red'), str_contains($value, 'rouge'), str_contains($value, 'rosso'), str_contains($value, 'tinto'), str_contains($value, 'rot') => WineColour::Red,
            str_contains($value, 'white'), str_contains($value, 'blanc'), str_contains($value, 'bianco'), str_contains($value, 'blanco'), str_contains($value, 'weiss'), str_contains($value, 'weiß') => WineColour::White,
            default => null,
        };
    }

    /**
     * @return array<int, string>|null
     */
    public function parseGrapes(?string $value): ?array
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $parts = preg_split('/[,;\/&|]|\s+and\s+/i', $value) ?: [];

        $grapes = array_values(array_filter(array_map('trim', $parts), fn ($g) => $g !== ''));
        $grapes = array_map(fn ($g) => $this->standardiseGrape($g), $grapes);

        return $grapes === [] ? null : $grapes;
    }

    public function parsePrice(?string $value): ?float
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        // Strip everything except digits, separators and sign.
        $clean = preg_replace('/[^0-9.,-]/', '', $value) ?? '';

        if ($clean === '' || $clean === '-') {
            return null;
        }

        $hasDot = str_contains($clean, '.');
        $hasComma = str_contains($clean, ',');

        if ($hasDot && $hasComma) {
            // Dot is the decimal point; commas are thousands separators.
            $clean = str_replace(',', '', $clean);
        } elseif ($hasComma) {
            // Only commas: a single comma with exactly two trailing digits is a
            // decimal (25,00); otherwise treat commas as thousands (1,200).
            $afterLast = substr($clean, (int) strrpos($clean, ',') + 1);
            $clean = strlen($afterLast) === 2 && substr_count($clean, ',') === 1
                ? str_replace(',', '.', $clean)
                : str_replace(',', '', $clean);
        }

        return is_numeric($clean) ? (float) $clean : null;
    }

    public function parseVintage(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (preg_match('/\b(19|20)\d{2}\b/', $value, $m)) {
            return (int) $m[0];
        }

        return null; // "NV", blank, etc.
    }

    public function parseFormatMl(?string $value): int
    {
        if ($value === null || trim($value) === '') {
            return 750;
        }

        $lower = strtolower($value);

        $named = [
            'magnum' => 1500,
            'jeroboam' => 3000,
            'half' => 375,
            'piccolo' => 200,
            'split' => 187,
        ];

        foreach ($named as $needle => $ml) {
            if (str_contains($lower, $needle)) {
                return $ml;
            }
        }

        if (preg_match('/([\d.]+)\s*(ml|cl|l|litre|liter)\b/i', $lower, $m)) {
            $number = (float) $m[1];

            return match (strtolower($m[2])) {
                'cl' => (int) round($number * 10),
                'l', 'litre', 'liter' => (int) round($number * 1000),
                default => (int) round($number),
            };
        }

        $digits = $this->parseInt($value);

        return $digits !== null && $digits > 0 ? $digits : 750;
    }

    private function parseInt(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $clean = preg_replace('/[^0-9-]/', '', $value) ?? '';

        return $clean === '' || $clean === '-' ? null : (int) $clean;
    }

    private function nullableString(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $value;
    }
}
