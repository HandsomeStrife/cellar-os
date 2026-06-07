<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('venue_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('quantity_units')->default(0);
            $table->decimal('last_purchase_price', 10, 2)->nullable();
            $table->string('last_purchase_currency', 3)->nullable();
            $table->timestamp('last_received_at')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['venue_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
