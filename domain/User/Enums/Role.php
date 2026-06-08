<?php

declare(strict_types=1);

namespace Domain\User\Enums;

/**
 * A user's role within their company.
 *
 *  - Owner   — full access, including billing.
 *  - Manager — everything except billing.
 *  - Member  — works only within the venues they're assigned to.
 */
enum Role: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Member = 'member';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Seniority rank (owner 3 > manager 2 > member 1).
     */
    public function rank(): int
    {
        return match ($this) {
            self::Owner => 3,
            self::Manager => 2,
            self::Member => 1,
        };
    }

    public function canManageBilling(): bool
    {
        return $this === self::Owner;
    }

    public function canManageTeam(): bool
    {
        return $this === self::Owner || $this === self::Manager;
    }

    /**
     * Whether this actor may create/assign a user at the given role —
     * "equivalent and lower": owners can assign any role, managers up to
     * manager, members none.
     */
    public function canAssignRole(self $target): bool
    {
        return $this->canManageTeam() && $this->rank() >= $target->rank();
    }

    /**
     * Whether this role has company-wide venue access (vs the user_venue pivot).
     */
    public function seesAllVenues(): bool
    {
        return $this === self::Owner || $this === self::Manager;
    }

    /**
     * @return array<string, string> value => label, for select options
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $role) => [$role->value => $role->getLabel()])
            ->all();
    }
}
