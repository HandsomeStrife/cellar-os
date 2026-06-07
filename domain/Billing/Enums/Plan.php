<?php

declare(strict_types=1);

namespace Domain\Billing\Enums;

enum Plan: string
{
    case Free = 'free';
    case Starter = 'starter';
    case Pro = 'pro';
    case Group = 'group';

    public function getLabel(): string
    {
        return match ($this) {
            self::Free => 'Free',
            self::Starter => 'Starter',
            self::Pro => 'Pro',
            self::Group => 'Group',
        };
    }

    /**
     * Rank within the upgrade ladder (free < starter < pro < group).
     */
    public function rank(): int
    {
        return match ($this) {
            self::Free => 0,
            self::Starter => 1,
            self::Pro => 2,
            self::Group => 3,
        };
    }

    public function atLeast(self $required): bool
    {
        return $this->rank() >= $required->rank();
    }

    public function can(Feature $feature): bool
    {
        return $this->atLeast($feature->minPlan());
    }
}
