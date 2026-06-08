<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Allocation of a connected supplier to specific venues. The venue
        // carries the company, so this is implicitly company-scoped.
        Schema::create('supplier_venue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['supplier_id', 'venue_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_venue');
    }
};
