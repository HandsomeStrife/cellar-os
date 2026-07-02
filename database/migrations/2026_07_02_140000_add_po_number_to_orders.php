<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Human-usable purchase-order numbers (trade convention: PO-2026-0042),
 * sequential per company per year — "#36B2C5C4" hex fragments are hostile on
 * the phone with a supplier. Existing orders are backfilled in creation order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('po_number')->nullable()->after('uuid');
            $table->index(['company_id', 'po_number']);
        });

        // Backfill: per company, in creation order, numbered within their year.
        $companyIds = DB::table('orders')->distinct()->pluck('company_id');

        foreach ($companyIds as $companyId) {
            $sequences = [];
            $orders = DB::table('orders')
                ->when($companyId === null, fn ($q) => $q->whereNull('company_id'))
                ->when($companyId !== null, fn ($q) => $q->where('company_id', $companyId))
                ->orderBy('created_at')
                ->orderBy('id')
                ->get(['id', 'created_at']);

            foreach ($orders as $order) {
                $year = substr((string) $order->created_at, 0, 4) ?: date('Y');
                $sequences[$year] = ($sequences[$year] ?? 0) + 1;
                DB::table('orders')->where('id', $order->id)->update([
                    'po_number' => sprintf('PO-%s-%04d', $year, $sequences[$year]),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'po_number']);
            $table->dropColumn('po_number');
        });
    }
};
