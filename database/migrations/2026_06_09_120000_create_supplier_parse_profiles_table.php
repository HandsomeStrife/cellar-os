<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A learned "recipe" for parsing a supplier's documents. Stored per
        // supplier and reused on the next upload so parsing is faster + more
        // accurate over time. For tabular files the recipe is a column mapping
        // (productField => header); for PDFs it is a structural description +
        // few-shot examples. Reviewer corrections refine the active profile.
        Schema::create('supplier_parse_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            // Profiles learned from a BUYER's document are scoped to that company
            // (their corrections/examples must not bleed to other tenants); ones
            // learned from portal/admin runs are global (null).
            $table->foreignId('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->string('mode'); // tabular | document
            $table->json('recipe');
            $table->string('model')->nullable();
            $table->decimal('confidence', 3, 2)->nullable(); // 0.00 - 1.00
            $table->foreignId('source_document_id')->nullable()
                ->constrained('supplier_documents')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['supplier_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_parse_profiles');
    }
};
