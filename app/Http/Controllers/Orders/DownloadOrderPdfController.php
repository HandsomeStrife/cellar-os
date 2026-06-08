<?php

declare(strict_types=1);

namespace App\Http\Controllers\Orders;

use Domain\Billing\Enums\Feature;
use Domain\Billing\Enums\Plan;
use Domain\Company\Repositories\CompanyRepository;
use Domain\Order\Repositories\OrderRepository;
use Domain\Order\Services\OrderPdfService;
use Domain\Supplier\Repositories\SupplierRepository;
use Domain\Venue\Repositories\VenueRepository;
use Illuminate\Http\Response;

class DownloadOrderPdfController
{
    public function __invoke(int $id): Response
    {
        // Same entitlement as the rest of the Orders feature.
        $company = (new CompanyRepository)->getLoggedInCompany();
        $plan = $company?->plan ?? Plan::Free;
        abort_unless($plan->can(Feature::CreatePurchaseOrders), 403);

        // Tenant guard: the order must belong to the current company.
        $order = $company ? (new OrderRepository)->findForCompany($id, $company->id) : null;
        abort_if($order === null, 404);

        $supplier = $order->supplier_id ? (new SupplierRepository)->find($order->supplier_id) : null;
        $venue = $order->venue_id ? (new VenueRepository)->find($order->venue_id) : null;

        $reference = $order->uuid ? substr($order->uuid, 0, 8) : (string) $order->id;

        return (new OrderPdfService)->generate($order, $supplier, $venue)
            ->download("purchase-order-{$reference}.pdf");
    }
}
