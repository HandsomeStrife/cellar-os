<?php

declare(strict_types=1);

use App\Livewire\SupplierPortal\Auth\ForgotPassword;
use App\Livewire\SupplierPortal\Auth\Login;
use App\Livewire\SupplierPortal\Auth\ResetPassword;
use Domain\Admin\Models\Admin;
use Domain\Supplier\Enums\SupplierStatus;
use Domain\Supplier\Models\Supplier;
use Domain\Supplier\Models\SupplierUser;
use Domain\Supplier\Notifications\SupplierPasswordSetupNotification;
use Domain\User\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;

it('renders the supplier login screen', function () {
    $this->get(route('supplier.login'))->assertOk()->assertSeeLivewire(Login::class);
});

it('authenticates a supplier user', function () {
    $user = SupplierUser::factory()->create(['password' => 'password']);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('supplier.dashboard'));

    $this->assertAuthenticatedAs($user, 'supplier');
});

it('rejects bad supplier credentials', function () {
    $user = SupplierUser::factory()->create(['password' => 'password']);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'wrong')
        ->call('login')
        ->assertHasErrors('email');

    $this->assertGuest('supplier');
});

it('blocks login when the supplier company is inactive', function () {
    $supplier = Supplier::factory()->create(['status' => SupplierStatus::Inactive->value]);
    $user = SupplierUser::factory()->create(['supplier_id' => $supplier->id, 'password' => 'password']);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasErrors('email');

    $this->assertGuest('supplier');
});

it('redirects guests from the supplier area to the supplier login', function () {
    $this->get(route('supplier.dashboard'))->assertRedirect(route('supplier.login'));
});

it('does not let a normal user or admin into the supplier area', function () {
    $this->actingAs(User::factory()->create()); // web guard only
    $this->get(route('supplier.dashboard'))->assertRedirect(route('supplier.login'));

    $this->actingAs(Admin::factory()->create(), 'admin');
    $this->get(route('supplier.dashboard'))->assertRedirect(route('supplier.login'));
});

it('does not authenticate a supplier user on the web guard', function () {
    $this->actingAs(SupplierUser::factory()->create(), 'supplier');

    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('logs out a supplier user', function () {
    $user = SupplierUser::factory()->create();

    $this->actingAs($user, 'supplier')
        ->post(route('supplier.logout'))
        ->assertRedirect(route('supplier.login'));

    $this->assertGuest('supplier');
});

it('emails an invite link on the supplier broker', function () {
    Notification::fake();
    $user = SupplierUser::factory()->invited()->create();

    Livewire::test(ForgotPassword::class)
        ->set('email', $user->email)
        ->call('sendResetLink')
        ->assertHasNoErrors();

    Notification::assertSentTo($user, SupplierPasswordSetupNotification::class);
});

it('lets an invited supplier user set their password and sign in', function () {
    $user = SupplierUser::factory()->invited()->create();
    $token = Password::broker('supplier_users')->createToken($user);

    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', $user->email)
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('resetPassword')
        ->assertHasNoErrors()
        ->assertRedirect(route('supplier.login'));

    expect(SupplierUser::find($user->id)->password)->not->toBeNull();

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'new-password')
        ->call('login')
        ->assertHasNoErrors();

    $this->assertAuthenticatedAs($user->fresh(), 'supplier');
});
