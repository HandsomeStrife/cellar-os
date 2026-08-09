<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional narrowing of a wine's type — "Sparkling Rosé" under Sparkling,
 * "Port" under Fortified. Always nullable: most wines don't have one, and a
 * wine with a sub-type is still returned when filtering by its parent type.
 *
 * The type itself stays in the long-standing `colour` column: renaming it
 * would break golden payloads, saved parse recipes and production in one go
 * for no gain. `Domain\Catalogue\Enums\WineType` is the domain language.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('sub_type')->nullable()->after('colour');
            $table->index(['colour', 'sub_type']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['colour', 'sub_type']);
            $table->dropColumn('sub_type');
        });
    }
};
