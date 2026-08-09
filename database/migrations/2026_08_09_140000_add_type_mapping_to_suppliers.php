<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-supplier translation of the wine-type words THEY use into ours.
 *
 * The shared vocabulary in NormaliseService covers what the trade agrees on
 * ("Skin Contact" is Orange everywhere). This is for the rest: a supplier's
 * house shorthand, a mis-spelling, a category we'd file differently. Learned
 * once by a human on the review screen and reused on that supplier's next
 * edition, exactly like `column_mapping`.
 *
 * Shape: { "<their label, lowercased>": {"type": "Orange", "sub_type": null} }
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->json('type_mapping')->nullable()->after('column_mapping');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('type_mapping');
        });
    }
};
