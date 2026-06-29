<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Catalogue\Enums\WineColour;
use Domain\Catalogue\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unitPrice = fake()->randomFloat(2, 5, 250);
        $formatMl = 750;

        return [
            'uuid' => (string) Str::uuid(),
            'wine_name' => fake()->words(3, true),
            'producer' => fake()->company(),
            'country' => fake()->country(),
            'region' => fake()->city(),
            'sub_region' => fake()->city(),
            'grape' => [fake()->word()],
            'colour' => fake()->randomElement(WineColour::cases())->value,
            'vintage' => (int) fake()->year(),
            'format_ml' => $formatMl,
            'case_size' => 6,
            'sold_by' => 'bottle',
            'unit_price' => number_format($unitPrice, 2, '.', ''),
            'pack_price' => null,
            'price_per_litre' => number_format($unitPrice / ($formatMl / 1000), 2, '.', ''),
            'stock' => fake()->numberBetween(0, 500),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
        ];
    }

    /**
     * Quoted by the case. Optionally pin the exact case (pack) price; otherwise
     * it derives from the per-bottle price × case size at display time.
     */
    public function soldByCase(int $caseSize = 6, ?float $packPrice = null): static
    {
        return $this->state(fn (array $attributes) => [
            'sold_by' => 'case',
            'case_size' => $caseSize,
            'pack_price' => $packPrice !== null ? number_format($packPrice, 2, '.', '') : null,
        ]);
    }
}
