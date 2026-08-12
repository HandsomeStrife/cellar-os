<?php

declare(strict_types=1);

use App\Livewire\Admin\CompanyCreate;
use App\Livewire\Admin\CompanyShow;
use Domain\Admin\Models\Admin;
use Domain\Billing\Enums\BillingArrangement;
use Domain\Billing\Enums\BillingInterval;
use Domain\Billing\Enums\Plan;
use Domain\Company\Actions\ProvisionCompanyAction;
use Domain\Company\Data\CompanyBillingData;
use Domain\Company\Data\ProvisionCompanyData;
use Domain\Company\Models\Company;
use Domain\Company\Repositories\CompanyRepository;
use Domain\User\Enums\Role;
use Domain\User\Models\User;
use Domain\User\Notifications\UserInviteNotification;
use Domain\Venue\Models\Venue;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

function signInAdmin(): Admin
{
    $admin = Admin::factory()->create();
    test()->actingAs($admin, 'admin');

    return $admin;
}

it('creates a company with a venue and an invited owner', function () {
    Notification::fake();
    signInAdmin();

    Livewire::test(CompanyCreate::class)
        ->set('name', 'Harbour & Vine')
        ->set('baseCurrency', 'GBP')
        ->set('plan', Plan::Group->value)
        ->set('trialDays', '30')
        ->set('ownerName', 'Ana Ruiz')
        ->set('ownerEmail', 'ana@harbourvine.test')
        ->call('create')
        ->assertHasNoErrors()
        ->assertRedirect();

    $company = Company::firstWhere('name', 'Harbour & Vine');
    $data = (new CompanyRepository)->find($company->id);

    expect($data->plan)->toBe(Plan::Group)
        ->and($data->onTrial())->toBeTrue()
        ->and($data->trialDaysRemaining())->toBe(30);

    $owner = User::firstWhere('email', 'ana@harbourvine.test');

    expect($owner->company_id)->toBe($company->id)
        ->and($owner->role)->toBe(Role::Owner)
        // No password until they follow the invite link.
        ->and($owner->password)->toBeNull();

    // A company with no venue can't hold stock or raise an order, so one is
    // always made — and the owner can actually see it.
    $venue = Venue::firstWhere('company_id', $company->id);

    expect($venue->name)->toBe('Harbour & Vine')
        ->and(DB::table('user_venue')->where('user_id', $owner->id)->where('venue_id', $venue->id)->exists())
        ->toBeTrue();

    Notification::assertSentTo($owner, UserInviteNotification::class);
});

it('creates a company without an owner, for a deal signed before anyone is named', function () {
    Notification::fake();
    signInAdmin();

    Livewire::test(CompanyCreate::class)
        ->set('name', 'Placeholder Cellars')
        ->call('create')
        ->assertHasNoErrors();

    $company = Company::firstWhere('name', 'Placeholder Cellars');

    expect($company)->not->toBeNull()
        ->and(User::where('company_id', $company->id)->count())->toBe(0)
        ->and(Venue::where('company_id', $company->id)->count())->toBe(1);

    Notification::assertNothingSent();
});

it('names the first venue after the company when none is given', function () {
    signInAdmin();

    Livewire::test(CompanyCreate::class)
        ->set('name', 'Ridge & Rill')
        ->set('venueName', 'The Rill Room')
        ->call('create')
        ->assertHasNoErrors();

    $company = Company::firstWhere('name', 'Ridge & Rill');

    expect(Venue::firstWhere('company_id', $company->id)->name)->toBe('The Rill Room');
});

it('records a negotiated price at creation', function () {
    signInAdmin();

    Livewire::test(CompanyCreate::class)
        ->set('name', 'Discount Wines')
        ->set('plan', Plan::Group->value)
        ->set('arrangement', BillingArrangement::Custom->value)
        ->set('customPrice', '99')
        ->set('customInterval', BillingInterval::Month->value)
        ->call('create')
        ->assertHasNoErrors();

    $data = (new CompanyRepository)->find(Company::firstWhere('name', 'Discount Wines')->id);

    expect($data->custom_price_amount)->toBe(9900)
        ->and($data->billingLabel())->toBe('£99.00 a month');
});

it('will not create a company on a custom arrangement with no price', function () {
    signInAdmin();

    Livewire::test(CompanyCreate::class)
        ->set('name', 'Vague Terms Ltd')
        ->set('arrangement', BillingArrangement::Custom->value)
        ->call('create')
        ->assertHasErrors('customPrice');

    expect(Company::where('name', 'Vague Terms Ltd')->exists())->toBeFalse();
});

it('refuses an owner email that already belongs to someone', function () {
    signInAdmin();
    User::factory()->create(['email' => 'taken@wine.test']);

    Livewire::test(CompanyCreate::class)
        ->set('name', 'Second Attempt Ltd')
        ->set('ownerName', 'Someone Else')
        ->set('ownerEmail', 'taken@wine.test')
        ->call('create')
        ->assertHasErrors('ownerEmail');

    // Nothing half-made: no company, no venue.
    expect(Company::where('name', 'Second Attempt Ltd')->exists())->toBeFalse();
});

it('refuses half an owner', function () {
    signInAdmin();

    Livewire::test(CompanyCreate::class)
        ->set('name', 'Half Owner Ltd')
        ->set('ownerEmail', 'nameless@wine.test')
        ->call('create')
        ->assertHasErrors('ownerName');
});

it('skips the invite when asked to', function () {
    Notification::fake();
    signInAdmin();

    Livewire::test(CompanyCreate::class)
        ->set('name', 'Quiet Start Ltd')
        ->set('ownerName', 'Pat Quiet')
        ->set('ownerEmail', 'pat@quiet.test')
        ->set('sendInvite', false)
        ->call('create')
        ->assertHasNoErrors();

    // The seat still exists, ready to be invited from the company page.
    expect(User::where('email', 'pat@quiet.test')->exists())->toBeTrue();

    Notification::assertNothingSent();
});

it('rolls the whole tenant back if any part of provisioning fails', function () {
    // The unique-email rule normally catches a clash before the action runs,
    // so the rollback path is never entered by the form. Go at the action
    // directly, which is what a CLI or API caller would do.
    signInAdmin();
    User::factory()->create(['email' => 'clash@wine.test']);

    $attempt = fn () => (new ProvisionCompanyAction)->execute(
        new ProvisionCompanyData(
            name: 'Rollback Ltd',
            base_currency: 'GBP',
            billing: new CompanyBillingData(plan: Plan::Pro),
            owner_name: 'Clashing Owner',
            owner_email: 'clash@wine.test',
        ),
    );

    expect($attempt)->toThrow(QueryException::class);

    // Nothing half-made: no company, and no orphan venue pointing at one.
    expect(Company::where('name', 'Rollback Ltd')->exists())->toBeFalse()
        ->and(Venue::where('name', 'Rollback Ltd')->exists())->toBeFalse();
});

it('gives an admin-added member access to the company venues', function () {
    // A member with no venue sees nothing at all. The create flow assigns
    // them; the "add people later" form on the company page must too, or the
    // seat it makes is inert.
    signInAdmin();
    $company = Company::factory()->create();
    $venue = Venue::factory()->create(['company_id' => $company->id]);

    Livewire::test(CompanyShow::class, ['uuid' => $company->uuid])
        ->set('newUserName', 'New Member')
        ->set('newUserEmail', 'member@later.test')
        ->set('newUserRole', Role::Member->value)
        ->call('addUser')
        ->assertHasNoErrors();

    $member = User::firstWhere('email', 'member@later.test');

    expect(DB::table('user_venue')->where('user_id', $member->id)->where('venue_id', $venue->id)->exists())
        ->toBeTrue();
});

it('refuses a role that is not a real role', function () {
    signInAdmin();
    $company = Company::factory()->create();

    Livewire::test(CompanyShow::class, ['uuid' => $company->uuid])
        ->set('newUserName', 'Sneaky')
        ->set('newUserEmail', 'sneaky@wine.test')
        ->set('newUserRole', 'superuser')
        ->call('addUser')
        ->assertHasErrors('newUserRole');

    expect(User::where('email', 'sneaky@wine.test')->exists())->toBeFalse();
});

it('keeps non-admins out', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('admin.companies.create'))->assertRedirect(route('admin.login'));

    Livewire::test(CompanyCreate::class)
        ->set('name', 'Sneaky Ltd')
        ->call('create')
        ->assertForbidden();

    expect(Company::where('name', 'Sneaky Ltd')->exists())->toBeFalse();
});
