<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Distinguishes "the supplier withheld this price" (POA/TBC) from "we have no
 * price" — see Domain\Catalogue\Enums\PriceState. A POA wine belongs in the
 * catalogue; a price-less one does not.
 *
 * `price_note` carries the supplier's own wording verbatim, which is often the
 * useful part ("tiny allocations from this grower — ask your rep").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('price_state')->default('priced')->after('unit_price');
            $table->text('price_note')->nullable()->after('price_state');
            $table->index('price_state');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['price_state']);
            $table->dropColumn(['price_state', 'price_note']);
        });
    }
};
