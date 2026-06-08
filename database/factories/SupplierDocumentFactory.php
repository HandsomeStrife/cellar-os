<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Supplier\Enums\SupplierDocumentStatus;
use Domain\Supplier\Models\Supplier;
use Domain\Supplier\Models\SupplierDocument;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SupplierDocument>
 */
class SupplierDocumentFactory extends Factory
{
    protected $model = SupplierDocument::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->slug().'.csv';

        return [
            'uuid' => (string) Str::uuid(),
            'supplier_id' => Supplier::factory(),
            'uploaded_by_supplier_user_id' => null,
            'title' => fake()->optional()->sentence(3),
            'file_name' => $name,
            'file_type' => 'text/csv',
            'file_size' => fake()->numberBetween(1024, 2_000_000),
            'storage_path' => 'supplier-documents/'.$name,
            'status' => SupplierDocumentStatus::AwaitingAnalysis->value,
            'analysis_notes' => null,
            'analysed_at' => null,
        ];
    }
}
