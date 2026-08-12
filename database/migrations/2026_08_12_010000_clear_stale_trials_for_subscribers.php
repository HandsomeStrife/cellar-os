<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Clear the trial date on companies that already converted.
     *
     * `trial_ends_at` is now cleared when a subscription goes active, but only
     * on events that arrive from here on. Anyone who converted BEFORE this
     * deploy still carries the date they trialled under, and would be demoted
     * the first time Stripe marks their card `past_due` — which is exactly the
     * dunning downgrade the listener was changed to prevent.
     *
     * Only touches companies with a genuinely live subscription, so a trial
     * still running is left exactly as it is.
     */
    public function up(): void
    {
        DB::table('companies')
            ->whereNotNull('trial_ends_at')
            ->whereIn('id', DB::table('subscriptions')
                ->select('company_id')
                ->whereIn('stripe_status', ['active', 'trialing'])
                ->whereNull('ends_at'))
            ->update(['trial_ends_at' => null]);
    }

    public function down(): void
    {
        // The dates are gone and cannot be reconstructed. Nothing to restore:
        // a company without a trial date keeps its plan, which is the safe
        // direction, and Stripe remains the record of what they pay.
    }
};
