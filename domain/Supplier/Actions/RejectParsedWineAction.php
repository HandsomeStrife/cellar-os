<?php

declare(strict_types=1);

namespace Domain\Supplier\Actions;

use Domain\Shared\Actions\AbstractAction;
use Domain\Supplier\Enums\ParsedWineStatus;
use Domain\Supplier\Models\ParsedWine;

class RejectParsedWineAction extends AbstractAction
{
    public function execute(int $parsedWineId): void
    {
        ParsedWine::where('id', $parsedWineId)
            ->update(['status' => ParsedWineStatus::Rejected->value]);
    }
}
