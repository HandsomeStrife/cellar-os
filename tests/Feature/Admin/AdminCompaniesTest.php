<?php

declare(strict_types=1);

use App\Livewire\Admin\Companies;
use App\Livewire\Admin\CompanyShow;
use Domain\Admin\Models\Admin;
use Domain\Billing\Enums\Plan;
use Domain\Company\Models\Company;
use Domain\Order\Models\Order;
use Domain\User\Enums\Role;
use Domain\User\Models\User;
use Domain\User\Notifications\UserInviteNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

it('lists companies for an admin', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
    Company::factory()->create(['name' => 'Acme Wines']);

    Livewire::test(Companies::class)->assertSee('Acme Wines');
});

it('changes a company plan', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
    $company = Company::factory()->onPlan(Plan::Free)->create();

    Livewire::test(CompanyShow::class, ['uuid' => $company->uuid])
        ->set('plan', Plan::Pro->value)
        ->call('setPlan')
        ->assertHasNoErrors();

    expect($company->fresh()->plan)->toBe(Plan::Pro);
});

it('adds a user to a company and sends an invite', function () {
    Notification::fake();
    $this->actingAs(Admin::factory()->create(), 'admin');
    $company = Company::factory()->create();

    Livewire::test(CompanyShow::class, ['uuid' => $company->uuid])
        ->set('newUserName', 'New Hire')
        ->set('newUserEmail', 'hire@co.test')
        ->set('newUserRole', Role::Manager->value)
        ->call('addUser')
        ->assertHasNoErrors();

    $user = User::where('email', 'hire@co.test')->first();
    expect($user)->not->toBeNull()
        ->and($user->company_id)->toBe($company->id)
        ->and($user->role)->toBe(Role::Manager);

    Notification::assertSentTo($user, UserInviteNotification::class);
});

it('deletes a company and cascades its users, venues and orders', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
    [$company, $owner, $venue] = makeTenant(Plan::Pro);
    $order = Order::factory()->create([
        'company_id' => $company->id,
        'venue_id' => $venue->id,
    ]);

    Livewire::test(CompanyShow::class, ['uuid' => $company->uuid])
        ->call('deleteCompany')
        ->assertRedirect(route('admin.companies'));

    $this->assertDatabaseMissing('companies', ['id' => $company->id]);
    $this->assertDatabaseMissing('users', ['id' => $owner->id]);
    $this->assertDatabaseMissing('venues', ['id' => $venue->id]);
    $this->assertDatabaseMissing('orders', ['id' => $order->id]);
});

it('forbids a non-admin from company management actions', function () {
    $this->actingAs(User::factory()->create()); // web guard only
    $company = Company::factory()->create();

    Livewire::test(CompanyShow::class, ['uuid' => $company->uuid])
        ->set('plan', Plan::Group->value)
        ->call('setPlan')
        ->assertForbidden();
});
