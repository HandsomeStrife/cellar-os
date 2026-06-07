<?php

declare(strict_types=1);

namespace Domain\Order\Models;

use Database\Factories\OrderItemFactory;
use Domain\Order\Data\OrderItemData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'wine_name',
        'quantity_units',
        'unit_price_at_order',
        'currency_at_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity_units' => 'integer',
            'unit_price_at_order' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function getData(): OrderItemData
    {
        return OrderItemData::fromModel($this);
    }

    protected static function newFactory(): OrderItemFactory
    {
        return OrderItemFactory::new();
    }
}
