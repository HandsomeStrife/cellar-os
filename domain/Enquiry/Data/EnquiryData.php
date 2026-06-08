<?php

declare(strict_types=1);

namespace Domain\Enquiry\Data;

use Carbon\CarbonImmutable;
use Domain\Enquiry\Enums\EnquiryStatus;
use Domain\Enquiry\Models\Enquiry;
use Domain\Shared\Data\AbstractData;

class EnquiryData extends AbstractData
{
    public function __construct(
        public ?int $id,
        public ?string $uuid,
        public string $name,
        public string $email,
        public ?string $company,
        public string $message,
        public EnquiryStatus $status = EnquiryStatus::New,
        public ?CarbonImmutable $handled_at = null,
        public ?CarbonImmutable $created_at = null,
    ) {}

    public static function fromModel(Enquiry $model): self
    {
        return new self(
            id: $model->id,
            uuid: $model->uuid,
            name: $model->name,
            email: $model->email,
            company: $model->company,
            message: $model->message,
            status: $model->status,
            handled_at: $model->handled_at,
            created_at: $model->created_at?->toImmutable(),
        );
    }

    public function toModel(): Enquiry
    {
        return Enquiry::findOrFail($this->id);
    }
}
