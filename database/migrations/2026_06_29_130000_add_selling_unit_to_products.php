<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Case-vs-unit pricing (Phase 2a). Some suppliers quote per bottle, some by the
 * case. `unit_price` stays the canonical per-bottle price (for cross-supplier
 * comparison/sort/filter); `sold_by` records the native selling unit and
 * `pack_price` holds the supplier's exact quoted case price when sold by the
 * case (null = derive from unit_price × case_size).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('sold_by')->default('bottle')->after('case_size');
            $table->decimal('pack_price', 10, 2)->nullable()->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['sold_by', 'pack_price']);
        });
    }
};
