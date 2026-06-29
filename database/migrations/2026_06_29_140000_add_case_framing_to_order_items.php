<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Order-by-the-case (Phase 2c). `quantity_units` stays the canonical bottle
 * count (so receive → inventory is unchanged); these snapshot HOW the wine was
 * sold at order time, so a case order displays "2 cases · £x/case" even if the
 * product's pricing later changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('sold_by_at_order')->default('bottle')->after('quantity_units');
            $table->integer('pack_size_at_order')->nullable()->after('sold_by_at_order');
            $table->decimal('pack_price_at_order', 10, 2)->nullable()->after('unit_price_at_order');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['sold_by_at_order', 'pack_size_at_order', 'pack_price_at_order']);
        });
    }
};
