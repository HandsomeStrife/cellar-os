<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Catalogue\Models\WineFact;
use Domain\Catalogue\Support\WineIdentity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WineFact>
 */
class WineFactFactory extends Factory
{
    protected $model = WineFact::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $producer = fake()->lastName().' Estate';
        $name = ucwords(fake()->words(2, true));

        return [
            'uuid' => (string) Str::uuid(),
            'identity_key' => WineIdentity::keyFor($producer, $name),
            'wine_name' => $name,
            'producer' => $producer,
            'country' => 'France',
            'region' => 'Bourgogne',
            'sub_region' => null,
            'grape' => ['Pinot Noir'],
            'colour' => 'Red',
            'field_sources' => [],
            'observations' => 1,
        ];
    }
}
