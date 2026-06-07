<?php

declare(strict_types=1);

namespace Domain\User\Models;

use Domain\User\Data\UserProfileData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    protected $fillable = [
        'user_id',
        'profession',
        'company_name',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getData(): UserProfileData
    {
        return UserProfileData::fromModel($this);
    }
}
