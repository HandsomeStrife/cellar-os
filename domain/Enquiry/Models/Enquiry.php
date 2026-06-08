<?php

declare(strict_types=1);

namespace Domain\Enquiry\Models;

use Database\Factories\EnquiryFactory;
use Domain\Enquiry\Data\EnquiryData;
use Domain\Enquiry\Enums\EnquiryStatus;
use Domain\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Enquiry extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'name',
        'email',
        'company',
        'message',
        'status',
        'handled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => EnquiryStatus::class,
            'handled_at' => 'immutable_datetime',
        ];
    }

    public function getData(): EnquiryData
    {
        return EnquiryData::fromModel($this);
    }

    protected static function newFactory(): EnquiryFactory
    {
        return EnquiryFactory::new();
    }
}
