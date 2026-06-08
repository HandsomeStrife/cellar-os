<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A company's supplier relationships ("My suppliers"): the company has
        // chosen to work with this supplier. Private suppliers are also linked
        // here on creation so the list is uniform.
        Schema::create('company_supplier', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'supplier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_supplier');
    }
};
