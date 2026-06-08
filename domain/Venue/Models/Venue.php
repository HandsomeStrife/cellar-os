<?php

declare(strict_types=1);

namespace Domain\Venue\Models;

use Database\Factories\VenueFactory;
use Domain\Shared\Traits\HasUuid;
use Domain\Venue\Data\VenueData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Venue extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'company_id',
        'name',
        'address',
        'city',
        'country',
        'base_currency',
    ];

    public function getData(): VenueData
    {
        return VenueData::fromModel($this);
    }

    protected static function newFactory(): VenueFactory
    {
        return VenueFactory::new();
    }
}
