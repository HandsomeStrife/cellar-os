<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Supplier\Enums\ParseMode;
use Domain\Supplier\Models\Supplier;
use Domain\Supplier\Models\SupplierParseProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SupplierParseProfile>
 */
class SupplierParseProfileFactory extends Factory
{
    protected $model = SupplierParseProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'supplier_id' => Supplier::factory(),
            'mode' => ParseMode::Tabular->value,
            'recipe' => ['mapping' => ['wine_name' => 'Wine', 'unit_price' => 'Price']],
            'model' => 'claude-opus-4-8',
            'confidence' => 0.9,
            'source_document_id' => null,
            'is_active' => true,
        ];
    }
}
