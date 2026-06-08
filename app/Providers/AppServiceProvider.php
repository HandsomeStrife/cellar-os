<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\UpdateCompanyPlanFromStripe;
use Domain\Company\Models\Company;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\WebhookReceived;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The billable tenant is the Company, not the User.
        Cashier::useCustomerModel(Company::class);

        Event::listen(WebhookReceived::class, UpdateCompanyPlanFromStripe::class);
    }
}
