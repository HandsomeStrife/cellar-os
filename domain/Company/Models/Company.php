<?php

declare(strict_types=1);

namespace Domain\Company\Models;

use Database\Factories\CompanyFactory;
use Domain\Billing\Casts\PlanCast;
use Domain\Billing\Enums\BillingArrangement;
use Domain\Billing\Enums\BillingInterval;
use Domain\Company\Data\CompanyData;
use Domain\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Billable;

/**
 * The Company is the tenant/account. It owns users (seats), venues and supplier
 * relationships, and carries the subscription plan + Laravel Cashier billing.
 */
class Company extends Model
{
    use Billable;
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'name',
        'base_currency',
        'plan',
        'billing_arrangement',
        'custom_price_amount',
        'custom_price_currency',
        'custom_price_interval',
        'billing_notes',
        'trial_ends_at',
    ];

    protected function casts(): array
    {
        return [
            'plan' => PlanCast::class,
            'billing_arrangement' => BillingArrangement::class,
            'custom_price_interval' => BillingInterval::class,
            'custom_price_amount' => 'integer',
            'trial_ends_at' => 'datetime',
        ];
    }

    public function getData(): CompanyData
    {
        return CompanyData::fromModel($this);
    }

    protected static function newFactory(): CompanyFactory
    {
        return CompanyFactory::new();
    }
}
