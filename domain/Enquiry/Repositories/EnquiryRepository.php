<?php

declare(strict_types=1);

namespace Domain\Enquiry\Repositories;

use Domain\Enquiry\Data\EnquiryData;
use Domain\Enquiry\Enums\EnquiryStatus;
use Domain\Enquiry\Models\Enquiry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EnquiryRepository
{
    public function paginate(?EnquiryStatus $status = null, int $perPage = 20): LengthAwarePaginator
    {
        return Enquiry::query()
            ->when($status, fn ($query) => $query->where('status', $status->value))
            ->latest()
            ->paginate($perPage)
            ->through(fn (Enquiry $enquiry) => $enquiry->getData());
    }

    public function findByUuid(string $uuid): ?EnquiryData
    {
        return Enquiry::where('uuid', $uuid)->first()?->getData();
    }

    public function newCount(): int
    {
        return Enquiry::where('status', EnquiryStatus::New->value)->count();
    }
}
