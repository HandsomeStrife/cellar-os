<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Source tracking for supplier documents: where a published list was
// downloaded from (so a weekly job can re-fetch it), the SHA-256 of the
// stored copy (so an unchanged file is a no-op), and archive/supersede
// columns so a refreshed edition replaces the old one without losing it.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_documents', function (Blueprint $table) {
            $table->string('source_url', 2048)->nullable()->after('storage_path');
            $table->char('content_sha256', 64)->nullable()->after('source_url');
            $table->timestamp('archived_at')->nullable()->after('analysed_at');
            $table->foreignId('superseded_by_document_id')
                ->nullable()
                ->after('archived_at')
                ->constrained('supplier_documents')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('superseded_by_document_id');
            $table->dropColumn(['source_url', 'content_sha256', 'archived_at']);
        });
    }
};
