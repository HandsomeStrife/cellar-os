<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Supplier-supplied portfolios / price sheets. Unlike `raw_uploads`
        // (which stores parsed rows for the catalogue import wizard), this
        // stores the original file on the private disk and tracks an analysis
        // lifecycle: AwaitingAnalysis -> Analysing -> Analysed | Failed.
        Schema::create('supplier_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('uploaded_by_supplier_user_id')->nullable()
                ->constrained('supplier_users')->nullOnDelete();
            $table->string('title')->nullable();
            $table->string('file_name');
            $table->string('file_type')->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('storage_path');
            $table->string('status')->default('AwaitingAnalysis');
            $table->text('analysis_notes')->nullable();
            $table->timestamp('analysed_at')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_documents');
    }
};
