<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The catalogue gained first-class filters/sorts on region, sub-region,
 * producer, vintage and price. Index those columns so the full-featured filter
 * panel stays fast across the ~12k-row catalogue.
 *
 * (country/region already has a composite index from the create migration; we
 * add a standalone region index for region-only filtering, plus the rest.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index('region');
            $table->index('sub_region');
            $table->index('producer');
            $table->index('vintage');
            $table->index('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['region']);
            $table->dropIndex(['sub_region']);
            $table->dropIndex(['producer']);
            $table->dropIndex(['vintage']);
            $table->dropIndex(['unit_price']);
        });
    }
};
