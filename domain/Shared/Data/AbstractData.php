<?php

declare(strict_types=1);

namespace Domain\Shared\Data;

use Livewire\Wireable;
use Spatie\LaravelData\Concerns\WireableData;
use Spatie\LaravelData\Data;

abstract class AbstractData extends Data implements Wireable
{
    use WireableData;
}
