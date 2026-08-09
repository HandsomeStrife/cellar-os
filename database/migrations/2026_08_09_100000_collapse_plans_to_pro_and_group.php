<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The plan line-up collapsed from four tiers to two: Pro and Group.
 *
 * Everything that used to be Free or Starter becomes Pro — nobody loses
 * access, and the entry tier now carries the whole day-to-day product.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('companies')
            ->whereIn('plan', ['free', 'starter'])
            ->update(['plan' => 'pro']);

        Schema::table('companies', function (Blueprint $table) {
            $table->string('plan')->default('pro')->change();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('plan')->default('free')->change();
        });

        // Companies are deliberately left on `pro`: which of them were
        // previously free vs starter is not recoverable.
    }
};
