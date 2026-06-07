<?php

declare(strict_types=1);

namespace Domain\User\Models;

use Database\Factories\UserFactory;
use Domain\Billing\Enums\Plan;
use Domain\Shared\Traits\HasUuid;
use Domain\User\Data\UserData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;

class User extends Authenticatable
{
    use Billable;
    use HasFactory;
    use HasUuid;
    use Notifiable;

    protected $fillable = [
        'full_name',
        'email',
        'password',
        'role',
        'plan',
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
            'plan' => Plan::class,
        ];
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function getData(): UserData
    {
        return UserData::fromModel($this);
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
