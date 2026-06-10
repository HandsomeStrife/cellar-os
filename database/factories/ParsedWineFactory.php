<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Catalogue\Data\ProductData;
use Domain\Supplier\Enums\ParsedWineStatus;
use Domain\Supplier\Models\ParsedWine;
use Domain\Supplier\Models\Supplier;
use Domain\Supplier\Models\SupplierDocument;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ParsedWine>
 */
class ParsedWineFactory extends Factory
{
    protected $model = ParsedWine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'supplier_document_id' => SupplierDocument::factory(),
            'supplier_id' => Supplier::factory(),
            // A full normalised ProductData snapshot, exactly as the parser stores it.
            'payload' => (new ProductData(
                id: null, uuid: null, supplier_id: null, raw_upload_id: null,
                wine_name: fake()->words(2, true), producer: fake()->lastName(),
                country: 'France', region: null, sub_region: null,
                grape: null, colour: null, vintage: 2022,
                format_ml: 750, case_size: 6,
                unit_price: '15.00', price_per_litre: '20.00', stock: 0,
                latitude: null, longitude: null,
            ))->toArray(),
            'status' => ParsedWineStatus::Proposed->value,
            'confidence' => 0.9,
            'source_ref' => null,
            'flag' => null,
        ];
    }
}
