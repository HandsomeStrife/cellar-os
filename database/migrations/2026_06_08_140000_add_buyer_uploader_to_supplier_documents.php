<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A supplier document can be uploaded either by a supplier-portal user
        // (existing column) OR by a buyer company (these columns) — e.g. a buyer
        // uploading the price sheet of a private/off-platform supplier. Buyer
        // documents are scoped to the uploading company.
        Schema::table('supplier_documents', function (Blueprint $table) {
            $table->foreignId('uploaded_by_company_id')->nullable()->after('uploaded_by_supplier_user_id')
                ->constrained('companies')->cascadeOnDelete();
            $table->foreignId('uploaded_by_user_id')->nullable()->after('uploaded_by_company_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('uploaded_by_company_id');
            $table->dropConstrainedForeignId('uploaded_by_user_id');
        });
    }
};
