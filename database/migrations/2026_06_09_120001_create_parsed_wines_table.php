<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The review queue: one proposed wine per row, produced by analysing a
        // supplier document. A human reviews/edits, then approves into the
        // catalogue (via Catalogue\UpsertProductAction) or rejects. `payload`
        // is a normalised ProductData snapshot (already through NormaliseService).
        Schema::create('parsed_wines', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('supplier_document_id')->constrained('supplier_documents')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->json('payload');
            $table->string('status')->default('proposed'); // proposed | approved | rejected
            $table->decimal('confidence', 3, 2)->nullable();
            $table->string('source_ref')->nullable(); // page / chunk provenance
            $table->string('flag')->nullable();        // e.g. low_confidence, missing_price
            $table->timestamps();

            $table->index(['supplier_document_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parsed_wines');
    }
};
