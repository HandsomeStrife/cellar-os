<?php

declare(strict_types=1);

namespace Domain\Supplier\Models;

use Database\Factories\SupplierFactory;
use Domain\Shared\Traits\HasUuid;
use Domain\Supplier\Data\SupplierData;
use Domain\Supplier\Enums\SupplierStatus;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'name',
        'contact',
        'email',
        'phone',
        'location',
        'status',
        'column_mapping',
    ];

    protected function casts(): array
    {
        return [
            'column_mapping' => 'array',
            'status' => SupplierStatus::class,
        ];
    }

    #[Scope]
    protected function active($query)
    {
        return $query->where('status', SupplierStatus::Active->value);
    }

    public function getData(): SupplierData
    {
        return SupplierData::fromModel($this);
    }

    protected static function newFactory(): SupplierFactory
    {
        return SupplierFactory::new();
    }
}
