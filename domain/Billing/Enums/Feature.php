<?php

declare(strict_types=1);

namespace Domain\Billing\Enums;

/**
 * Gated features and the minimum plan required for each, mirroring the
 * upstream `shared/plans.ts` FEATURES map.
 */
enum Feature: string
{
    // Imports
    case ImportSupplierLists = 'importSupplierLists';
    case PdfImport = 'pdfImport';

    // Ordering
    case CreatePurchaseOrders = 'createPOs';
    case SendPurchaseOrderEmail = 'sendPOEmail';

    // Inventory
    case Inventory = 'inventory';
    case InventoryArchive = 'inventoryArchive';
    case ManualInventoryAdd = 'manualInventoryAdd';
    case InventoryAttachments = 'inventoryAttachments';

    // Multi-venue
    case MultiVenue = 'multiVenue';

    public function minPlan(): Plan
    {
        return match ($this) {
            self::ImportSupplierLists,
            self::CreatePurchaseOrders,
            self::SendPurchaseOrderEmail,
            self::Inventory => Plan::Starter,

            self::PdfImport,
            self::InventoryArchive,
            self::ManualInventoryAdd,
            self::InventoryAttachments => Plan::Pro,

            self::MultiVenue => Plan::Group,
        };
    }
}
