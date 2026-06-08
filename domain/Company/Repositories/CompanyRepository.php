<?php

declare(strict_types=1);

namespace Domain\Company\Repositories;

use Domain\Company\Data\CompanyData;
use Domain\Company\Models\Company;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class CompanyRepository
{
    public function find(int $id): ?CompanyData
    {
        return Company::find($id)?->getData();
    }

    public function paginate(?string $term = null, int $perPage = 20): LengthAwarePaginator
    {
        return Company::query()
            ->when($term !== null && $term !== '', fn ($q) => $q->where('name', 'like', "%{$term}%"))
            ->orderBy('name')
            ->paginate($perPage)
            ->through(fn (Company $company) => $company->getData());
    }

    public function findByUuid(string $uuid): ?CompanyData
    {
        return Company::where('uuid', $uuid)->first()?->getData();
    }

    public function findByStripeId(string $stripeId): ?CompanyData
    {
        return Company::where('stripe_id', $stripeId)->first()?->getData();
    }

    public function count(): int
    {
        return Company::count();
    }

    /**
     * The company of the currently authenticated user (`web` guard), or null.
     *
     * Domain/app code MUST resolve the tenant through this rather than reading
     * the company off the user model directly.
     */
    public function getLoggedInCompany(): ?CompanyData
    {
        $companyId = Auth::user()?->company_id;

        return $companyId ? Company::find($companyId)?->getData() : null;
    }
}
