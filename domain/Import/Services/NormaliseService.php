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

        $pricePerLitre = $unitPrice !== null && $formatMl > 0
            ? round($unitPrice / ($formatMl / 1000), 2)
            : null;

        return new ProductData(
            id: null,
            uuid: null,
            supplier_id: $supplierId,
            raw_upload_id: $rawUploadId,
            wine_name: $wineName,
            producer: $this->nullableString($value('producer')),
            country: $this->nullableString($value('country')),
            region: $this->nullableString($value('region')),
            sub_region: $this->nullableString($value('sub_region')),
            grape: $this->parseGrapes($value('grape')),
            colour: $this->normaliseColour($value('colour')),
            vintage: $this->parseVintage($value('vintage')),
            format_ml: $formatMl,
            case_size: $this->parseInt($value('case_size')) ?? 6,
            unit_price: $unitPrice !== null ? number_format($unitPrice, 2, '.', '') : null,
            price_per_litre: $pricePerLitre !== null ? number_format($pricePerLitre, 2, '.', '') : null,
            stock: $this->parseInt($value('stock')) ?? 0,
            latitude: null,
            longitude: null,
        );
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

        $parts = preg_split('/[,;\/&]|\s+and\s+/i', $value) ?: [];

        $grapes = array_values(array_filter(array_map('trim', $parts), fn ($g) => $g !== ''));

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
