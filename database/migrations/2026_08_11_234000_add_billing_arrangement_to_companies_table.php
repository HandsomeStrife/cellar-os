<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How a company pays, kept apart from what it can do.
     *
     * `plan` stays the entitlement ladder. These columns record the commercial
     * arrangement behind it: comped, or a negotiated amount. `trial_ends_at`
     * already exists (Cashier puts it on the billable), so trials need no
     * column of their own — only somewhere to say what happens when one ends.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('billing_arrangement')->default('standard')->after('plan');
            // Minor units, so a price is never a float. Nullable: only a
            // custom arrangement has one.
            $table->unsignedInteger('custom_price_amount')->nullable()->after('billing_arrangement');
            $table->string('custom_price_currency', 3)->nullable()->after('custom_price_amount');
            $table->string('custom_price_interval')->nullable()->after('custom_price_currency');
            // Why the arrangement exists, for whoever finds it in a year.
            $table->text('billing_notes')->nullable()->after('custom_price_interval');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'billing_arrangement',
                'custom_price_amount',
                'custom_price_currency',
                'custom_price_interval',
                'billing_notes',
            ]);
        });
    }
};
