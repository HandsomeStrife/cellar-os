<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Liv-ex LWIN reference data (Creative Commons, free) — the wine
        // trade's canonical identifiers. Third-party REFERENCE data, refreshed
        // from the published file via wine:lwin-refresh; deliberately separate
        // from wine_facts (our own knowledge) and excluded from golden
        // snapshots (always re-importable from the source file on disk).
        Schema::create('lwins', function (Blueprint $table) {
            $table->id();
            $table->string('lwin', 7)->unique();   // LWIN7 — one wine/label
            $table->string('status', 30)->nullable();
            $table->string('display_name')->nullable();
            $table->string('producer_title')->nullable();
            $table->string('producer_name')->nullable();
            $table->string('wine')->nullable();
            $table->string('country')->nullable();
            $table->string('region')->nullable();
            $table->string('sub_region')->nullable();
            $table->string('site')->nullable();
            $table->string('parcel')->nullable();
            $table->string('colour', 50)->nullable();
            $table->string('type', 50)->nullable();
            $table->string('sub_type', 80)->nullable();
            $table->string('designation')->nullable();
            $table->string('classification')->nullable();
            $table->string('first_vintage', 10)->nullable();
            $table->string('final_vintage', 10)->nullable();
            $table->string('reference')->nullable();
            // Normalised lookup keys (WineIdentity folding) for matching.
            $table->string('identity_key', 250)->nullable()->index(); // producer|wine
            $table->string('name_key', 250)->nullable()->index();     // display name
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lwins');
    }
};
