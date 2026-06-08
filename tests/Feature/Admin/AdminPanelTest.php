<?php

declare(strict_types=1);

use App\Livewire\Admin\Auth\Login;
use App\Livewire\Admin\Users;
use Domain\Admin\Models\Admin;
use Domain\User\Models\User;
use Livewire\Livewire;

it('renders the admin login screen', function () {
    $this->get(route('admin.login'))->assertOk()->assertSeeLivewire(Login::class);
});

it('authenticates an admin', function () {
    $admin = Admin::factory()->create(['password' => 'password']);

    Livewire::test(Login::class)
        ->set('email', $admin->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($admin, 'admin');
});

it('rejects bad admin credentials', function () {
    $admin = Admin::factory()->create(['password' => 'password']);

    Livewire::test(Login::class)
        ->set('email', $admin->email)
        ->set('password', 'wrong')
        ->call('login')
        ->assertHasErrors('email');

    $this->assertGuest('admin');
});

it('redirects guests from the admin area to the admin login', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
});

it('does not let a normal user into the admin area', function () {
    $this->actingAs(User::factory()->create()); // web guard only

    $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
});

it('does not authenticate an admin on the web guard', function () {
    $this->actingAs(Admin::factory()->create(), 'admin'); // admin guard only

    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('renders the admin dashboard for an admin', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');

    $this->get(route('admin.dashboard'))->assertOk()->assertSee('Users');
});

it('lists users', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
    User::factory()->create(['email' => 'member@example.test']);

    Livewire::test(Users::class)->assertSee('member@example.test');
});

it('deletes a user', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
    $user = User::factory()->create();

    Livewire::test(Users::class)->call('deleteUser', $user->id);

    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});

it('forbids a web user from invoking admin actions directly', function () {
    // A logged-in regular user (not on the admin guard) hitting the component action.
    $this->actingAs(User::factory()->create()); // web guard only
    $target = User::factory()->create();

    Livewire::test(Users::class)->call('deleteUser', $target->id)->assertForbidden();

    $this->assertDatabaseHas('users', ['id' => $target->id]);
});

it('forbids a guest from invoking admin actions directly', function () {
    $target = User::factory()->create();

    Livewire::test(Users::class)->call('deleteUser', $target->id)->assertForbidden();

    $this->assertDatabaseHas('users', ['id' => $target->id]);
});

it('throttles repeated failed admin logins', function () {
    $admin = Admin::factory()->create(['password' => 'password']);

    foreach (range(1, 5) as $i) {
        Livewire::test(Login::class)
            ->set('email', $admin->email)
            ->set('password', 'wrong')
            ->call('login');
    }

    Livewire::test(Login::class)
        ->set('email', $admin->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasErrors('email');

    $this->assertGuest('admin');
});

it('logs out an admin', function () {
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')
        ->post(route('admin.logout'))
        ->assertRedirect(route('admin.login'));

    $this->assertGuest('admin');
});
