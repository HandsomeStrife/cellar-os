<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cross-supplier wine KNOWLEDGE (never pricing): objective attributes of
        // a wine (grape, colour, origin) accumulated from every list parsed in
        // the platform, keyed on a normalised producer+name identity. Used to
        // fill gaps when one supplier's list omits what another's provides —
        // displayed to buyers with a generic "another vendor" note; the source
        // supplier is recorded internally (field_sources) but never shown.
        Schema::create('wine_facts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('identity_key', 250)->unique();
            $table->string('wine_name');
            $table->string('producer')->nullable();
            $table->string('country')->nullable();
            $table->string('region')->nullable();
            $table->string('sub_region')->nullable();
            $table->json('grape')->nullable();
            $table->string('colour')->nullable();
            $table->json('field_sources')->nullable(); // field => {supplier_id, observed_at}
            $table->json('field_conflicts')->nullable(); // field => disagreement count — contested fields are not displayed
            $table->unsignedInteger('observations')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wine_facts');
    }
};
