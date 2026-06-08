<?php

declare(strict_types=1);

namespace Domain\Enquiry\Actions;

use Domain\Enquiry\Models\Enquiry;
use Domain\Shared\Actions\AbstractAction;

class DeleteEnquiryAction extends AbstractAction
{
    public function execute(string $uuid): void
    {
        Enquiry::where('uuid', $uuid)->firstOrFail()->delete();
    }
}
