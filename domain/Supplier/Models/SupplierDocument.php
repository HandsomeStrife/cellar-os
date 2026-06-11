<?php

declare(strict_types=1);

namespace Domain\Supplier\Models;

use Database\Factories\SupplierDocumentFactory;
use Domain\Shared\Traits\HasUuid;
use Domain\Supplier\Data\SupplierDocumentData;
use Domain\Supplier\Enums\SupplierDocumentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierDocument extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'supplier_id',
        'uploaded_by_supplier_user_id',
        'uploaded_by_company_id',
        'uploaded_by_user_id',
        'title',
        'file_name',
        'file_type',
        'file_size',
        'storage_path',
        'source_url',
        'content_sha256',
        'status',
        'analysis_notes',
        'analysed_at',
        'archived_at',
        'superseded_by_document_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => SupplierDocumentStatus::class,
            'file_size' => 'integer',
            'analysed_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function getData(): SupplierDocumentData
    {
        return SupplierDocumentData::fromModel($this);
    }

    protected static function newFactory(): SupplierDocumentFactory
    {
        return SupplierDocumentFactory::new();
    }
}
