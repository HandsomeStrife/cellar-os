<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // LWIN7 link on catalogue entities. lwin_source records how the match
        // was made: identity (producer|wine key), name (display name), llm
        // (model-assisted), or list (parsed straight from a supplier list).
        Schema::table('products', function (Blueprint $table) {
            $table->string('lwin', 7)->nullable()->index();
            $table->string('lwin_source', 20)->nullable();
        });

        Schema::table('wine_facts', function (Blueprint $table) {
            $table->string('lwin', 7)->nullable()->index();
            $table->string('lwin_source', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['lwin', 'lwin_source']);
        });

        Schema::table('wine_facts', function (Blueprint $table) {
            $table->dropColumn(['lwin', 'lwin_source']);
        });
    }
};
