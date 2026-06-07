<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('raw_upload_id')->nullable()->constrained('raw_uploads')->nullOnDelete();
            $table->string('wine_name');
            $table->string('producer')->nullable();
            $table->string('country')->nullable();
            $table->string('region')->nullable();
            $table->string('sub_region')->nullable();
            $table->json('grape')->nullable();
            $table->string('colour')->nullable();
            $table->integer('vintage')->nullable();
            $table->integer('format_ml')->default(750);
            $table->integer('case_size')->default(6);
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->decimal('price_per_litre', 10, 2)->nullable();
            $table->integer('stock')->default(0);
            $table->decimal('latitude', 10, 6)->nullable();
            $table->decimal('longitude', 10, 6)->nullable();
            $table->timestamps();

            $table->index(['country', 'region']);
            $table->index('colour');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
