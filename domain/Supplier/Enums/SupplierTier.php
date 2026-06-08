<?php

declare(strict_types=1);

namespace Domain\Supplier\Enums;

use Carbon\CarbonInterface;

/**
 * How a supplier came to be in the system:
 *
 *  - Private   — a buyer added it themselves (off-platform merchant). Visible
 *                only to the company that created it; that company may edit it.
 *  - Listed    — admin-added, public and discoverable, but not yet onboarded.
 *  - Onboarded — has claimed a portal account and self-manages its profile.
 */
enum SupplierTier: string
{
    case Private = 'private';
    case Listed = 'listed';
    case Onboarded = 'onboarded';

    public function getLabel(): string
    {
        return match ($this) {
            self::Private => 'Private',
            self::Listed => 'Listed',
            self::Onboarded => 'Onboarded',
        };
    }

    /**
     * Badge colour token (see x-badge).
     */
    public function getColour(): string
    {
        return match ($this) {
            self::Private => 'gray',
            self::Listed => 'blue',
            self::Onboarded => 'emerald',
        };
    }

    public function isPublic(): bool
    {
        return $this !== self::Private;
    }

    /**
     * Derive the tier from the supplier's columns.
     */
    public static function derive(?int $createdByCompanyId, ?CarbonInterface $onboardedAt): self
    {
        return match (true) {
            $onboardedAt !== null => self::Onboarded,
            $createdByCompanyId !== null => self::Private,
            default => self::Listed,
        };
    }
}
