<?php

declare(strict_types=1);

namespace Domain\Supplier\Models;

use Database\Factories\SupplierUserFactory;
use Domain\Shared\Traits\HasUuid;
use Domain\Supplier\Data\SupplierUserData;
use Domain\Supplier\Notifications\SupplierPasswordSetupNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Login accounts for the supplier portal. A supplier (company) may have many
 * users. Authenticated via the `supplier` guard — an entirely separate domain
 * from end users (`web`) and admins (`admin`).
 */
class SupplierUser extends Authenticatable
{
    use HasFactory;
    use HasUuid;
    use Notifiable;

    protected $fillable = [
        'supplier_id',
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Route the reset/invite email to the supplier portal's own reset route
     * rather than the default end-user one.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new SupplierPasswordSetupNotification($token));
    }

    public function getData(): SupplierUserData
    {
        return SupplierUserData::fromModel($this);
    }

    protected static function newFactory(): SupplierUserFactory
    {
        return SupplierUserFactory::new();
    }
}
