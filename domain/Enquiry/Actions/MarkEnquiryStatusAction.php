<?php

declare(strict_types=1);

namespace Domain\Enquiry\Actions;

use Domain\Enquiry\Data\EnquiryData;
use Domain\Enquiry\Enums\EnquiryStatus;
use Domain\Enquiry\Models\Enquiry;
use Domain\Shared\Actions\AbstractAction;

class MarkEnquiryStatusAction extends AbstractAction
{
    public function execute(string $uuid, EnquiryStatus $status): EnquiryData
    {
        $enquiry = Enquiry::where('uuid', $uuid)->firstOrFail();

        $enquiry->update([
            'status' => $status,
            'handled_at' => $status === EnquiryStatus::New ? null : now(),
        ]);

        return $enquiry->refresh()->getData();
    }
}
