<?php

declare(strict_types=1);

namespace Domain\Admin\Models;

use Database\Factories\AdminFactory;
use Domain\Admin\Data\AdminData;
use Domain\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Administrators are an entirely separate authentication domain from end
 * users: their own `admins` table, the `admin` auth guard, and no Cashier /
 * plan / billing concerns.
 */
class Admin extends Authenticatable
{
    use HasFactory;
    use HasUuid;
    use Notifiable;

    protected $fillable = [
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

    public function getData(): AdminData
    {
        return AdminData::fromModel($this);
    }

    protected static function newFactory(): AdminFactory
    {
        return AdminFactory::new();
    }
}
