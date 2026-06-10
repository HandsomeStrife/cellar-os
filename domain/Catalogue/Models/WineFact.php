<?php

declare(strict_types=1);

namespace Domain\Catalogue\Models;

use Database\Factories\WineFactFactory;
use Domain\Catalogue\Data\WineFactData;
use Domain\Catalogue\Enums\WineColour;
use Domain\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WineFact extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'identity_key',
        'wine_name',
        'producer',
        'country',
        'region',
        'sub_region',
        'grape',
        'colour',
        'field_sources',
        'field_conflicts',
        'observations',
    ];

    protected function casts(): array
    {
        return [
            'grape' => 'array',
            'colour' => WineColour::class,
            'field_sources' => 'array',
            'field_conflicts' => 'array',
            'observations' => 'integer',
        ];
    }

    public function getData(): WineFactData
    {
        return WineFactData::fromModel($this);
    }

    protected static function newFactory(): WineFactFactory
    {
        return WineFactFactory::new();
    }
}
