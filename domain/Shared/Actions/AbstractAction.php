<?php

declare(strict_types=1);

namespace Domain\Shared\Actions;

abstract class AbstractAction
{
    protected function beforeExecute(): void {}

    protected function afterExecute(): void {}
}
