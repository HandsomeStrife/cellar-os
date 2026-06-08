<?php

declare(strict_types=1);

namespace Domain\Enquiry\Actions;

use Domain\Enquiry\Data\EnquiryData;
use Domain\Enquiry\Enums\EnquiryStatus;
use Domain\Enquiry\Models\Enquiry;
use Domain\Shared\Actions\AbstractAction;

class StoreEnquiryAction extends AbstractAction
{
    public function execute(EnquiryData $data): EnquiryData
    {
        $enquiry = Enquiry::create([
            'name' => $data->name,
            'email' => $data->email,
            'company' => $data->company,
            'message' => $data->message,
            'status' => EnquiryStatus::New,
        ]);

        return $enquiry->getData();
    }
}
