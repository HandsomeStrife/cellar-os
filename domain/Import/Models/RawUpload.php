<?php

declare(strict_types=1);

namespace Domain\Import\Models;

use Database\Factories\RawUploadFactory;
use Domain\Import\Data\RawUploadData;
use Domain\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RawUpload extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'supplier_id',
        'uploaded_by',
        'file_name',
        'file_type',
        'row_count',
        'column_mapping',
        'rows',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'column_mapping' => 'array',
            'rows' => 'array',
            'row_count' => 'integer',
        ];
    }

    public function getData(): RawUploadData
    {
        return RawUploadData::fromModel($this);
    }

    protected static function newFactory(): RawUploadFactory
    {
        return RawUploadFactory::new();
    }
}
