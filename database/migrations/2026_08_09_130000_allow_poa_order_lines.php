<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A POA wine can be ordered — the buyer still needs to ask the supplier for it
 * — so an order line may legitimately have no price at the time it is raised.
 * A null `unit_price_at_order` means POA: the line prints as POA on the PO and
 * contributes nothing to the order total, which the PO says in as many words.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('unit_price_at_order', 10, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('unit_price_at_order', 10, 2)->nullable(false)->change();
        });
    }
};
