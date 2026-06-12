<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Listing lifecycle for catalogue products: when a wine was last seen in its
// supplier's current list (`last_seen_at`), which document edition claimed it
// (`source_document_id`, environment-local), and whether it has dropped out
// of the supplier's current edition (`archived_at` — hidden from browse/map
// but kept intact for inventory/order references; un-archives on reappearing).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->timestamp('last_seen_at')->nullable()->after('stock');
            $table->timestamp('archived_at')->nullable()->after('last_seen_at')->index();
            $table->foreignId('source_document_id')
                ->nullable()
                ->after('archived_at')
                ->constrained('supplier_documents')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_document_id');
            $table->dropIndex(['archived_at']);
            $table->dropColumn(['last_seen_at', 'archived_at']);
        });
    }
};
