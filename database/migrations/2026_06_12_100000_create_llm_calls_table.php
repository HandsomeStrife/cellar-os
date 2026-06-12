<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Cost ledger for every billable Claude API call (parsing, profile studies,
// LWIN matching). Written best-effort by ClaudeClient; surfaced in the admin
// console at /admin/costs.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('llm_calls', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('purpose', 40)->index();
            $table->string('model', 64);
            $table->unsignedInteger('input_tokens');
            $table->unsignedInteger('output_tokens');
            $table->decimal('cost_usd', 10, 6);
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_document_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llm_calls');
    }
};
