<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            // Supplier tier derives from these two columns:
            //  - created_by_company_id set  → Private (a buyer's own off-platform supplier)
            //  - both null                  → Listed (admin-added, public, not onboarded)
            //  - onboarded_at set           → Onboarded (claimed, has a portal account)
            // cascadeOnDelete: a deleted company's private suppliers go with it,
            // so they can't leak into the public Discover pool.
            $table->foreignId('created_by_company_id')->nullable()->after('id')
                ->constrained('companies')->cascadeOnDelete();
            $table->timestamp('onboarded_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_company_id');
            $table->dropColumn('onboarded_at');
        });
    }
};
