<?php

declare(strict_types=1);

namespace Domain\Inventory\Models;

use Database\Factories\InventoryItemFactory;
use Domain\Inventory\Data\InventoryItemData;
use Domain\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'venue_id',
        'product_id',
        'quantity_units',
        'last_purchase_price',
        'last_purchase_currency',
        'last_received_at',
        'is_archived',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity_units' => 'integer',
            'last_purchase_price' => 'decimal:2',
            'last_received_at' => 'datetime',
            'is_archived' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(InventoryAttachment::class);
    }

    #[Scope]
    protected function active($query)
    {
        return $query->where('is_archived', false);
    }

    public function getData(): InventoryItemData
    {
        return InventoryItemData::fromModel($this);
    }

    protected static function newFactory(): InventoryItemFactory
    {
        return InventoryItemFactory::new();
    }
}
