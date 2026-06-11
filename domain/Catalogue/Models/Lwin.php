<?php

declare(strict_types=1);

namespace Domain\Catalogue\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A row of the Liv-ex LWIN reference database (third-party, Creative
 * Commons). Reference data only — no HasUuid/factory ceremony; refreshed
 * wholesale by wine:lwin-refresh.
 */
class Lwin extends Model
{
    protected $fillable = [
        'lwin',
        'status',
        'display_name',
        'producer_title',
        'producer_name',
        'wine',
        'country',
        'region',
        'sub_region',
        'site',
        'parcel',
        'colour',
        'type',
        'sub_type',
        'designation',
        'classification',
        'first_vintage',
        'final_vintage',
        'reference',
        'identity_key',
        'name_key',
    ];
}
