<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Import\Models\RawUpload;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RawUpload>
 */
class RawUploadFactory extends Factory
{
    protected $model = RawUpload::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'file_name' => fake()->word().'.csv',
            'file_type' => 'text/csv',
            'row_count' => fake()->numberBetween(1, 1000),
            'column_mapping' => null,
            'rows' => null,
            'status' => 'pending',
        ];
    }
}
