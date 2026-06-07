<?php

declare(strict_types=1);

namespace Domain\Order\Models;

use Database\Factories\OrderFactory;
use Domain\Order\Data\OrderData;
use Domain\Order\Enums\OrderStatus;
use Domain\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'supplier_id',
        'venue_id',
        'created_by',
        'status',
        'total',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'total' => 'decimal:2',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function getData(): OrderData
    {
        return OrderData::fromModel($this);
    }

    protected static function newFactory(): OrderFactory
    {
        return OrderFactory::new();
    }
}
