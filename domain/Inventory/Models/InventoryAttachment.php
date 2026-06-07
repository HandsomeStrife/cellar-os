<?php

declare(strict_types=1);

namespace Domain\Inventory\Models;

use Database\Factories\InventoryAttachmentFactory;
use Domain\Inventory\Data\InventoryAttachmentData;
use Domain\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAttachment extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'inventory_item_id',
        'uploaded_by',
        'file_name',
        'file_type',
        'file_size',
        'storage_path',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function getData(): InventoryAttachmentData
    {
        return InventoryAttachmentData::fromModel($this);
    }

    protected static function newFactory(): InventoryAttachmentFactory
    {
        return InventoryAttachmentFactory::new();
    }
}
