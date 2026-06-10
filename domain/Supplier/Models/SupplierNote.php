<?php

declare(strict_types=1);

namespace Domain\Supplier\Models;

use Database\Factories\SupplierNoteFactory;
use Domain\Shared\Traits\HasUuid;
use Domain\Supplier\Data\SupplierNoteData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierNote extends Model
{
    use HasFactory;
    use HasUuid;

    protected $fillable = [
        'supplier_id',
        'admin_id',
        'note',
    ];

    public function getData(): SupplierNoteData
    {
        return SupplierNoteData::fromModel($this);
    }

    protected static function newFactory(): SupplierNoteFactory
    {
        return SupplierNoteFactory::new();
    }
}
