<?php

declare(strict_types=1);

namespace Domain\Order\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Domain\Order\Data\OrderData;
use Domain\Supplier\Data\SupplierData;
use Domain\Venue\Data\VenueData;

class OrderPdfService
{
    public function generate(OrderData $order, ?SupplierData $supplier, ?VenueData $venue): DomPdf
    {
        return Pdf::loadView('pdf.order', [
            'order' => $order,
            'supplier' => $supplier,
            'venue' => $venue,
        ]);
    }
}
