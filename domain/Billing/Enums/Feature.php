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

    public function label(): string
    {
        return match ($this) {
            self::ImportSupplierLists => 'Import supplier price lists',
            self::PdfImport => 'PDF price-list import',
            self::CreatePurchaseOrders => 'Create purchase orders',
            self::SendPurchaseOrderEmail => 'Email orders to suppliers',
            self::Inventory => 'Inventory tracking',
            self::InventoryArchive => 'Archive inventory lines',
            self::ManualInventoryAdd => 'Manually add stock',
            self::InventoryAttachments => 'Invoice & tasting-note attachments',
            self::MultiVenue => 'Multiple venues',
        };
    }

    /**
     * Features first unlocked at the given plan tier.
     *
     * @return array<int, self>
     */
    public static function unlockedAt(Plan $plan): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $feature) => $feature->minPlan() === $plan,
        ));
    }

    public function minPlan(): Plan
    {
        return match ($this) {
            // Pro is the entry tier and carries the whole day-to-day product;
            // Group's only addition is trading across more than one venue.
            self::ImportSupplierLists,
            self::PdfImport,
            self::CreatePurchaseOrders,
            self::SendPurchaseOrderEmail,
            self::Inventory,
            self::InventoryArchive,
            self::ManualInventoryAdd,
            self::InventoryAttachments => Plan::Pro,

            self::MultiVenue => Plan::Group,
        };
    }
}
