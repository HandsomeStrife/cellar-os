<?php

declare(strict_types=1);

use App\Livewire\Auth\Login;
use Domain\User\Models\User;
use Livewire\Livewire;

it('renders the login screen', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSeeLivewire(Login::class);
});

it('authenticates with valid credentials', function () {
    $user = User::factory()->create(['password' => 'password']);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('rejects an invalid password', function () {
    $user = User::factory()->create(['password' => 'password']);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors('email');

    $this->assertGuest();
});

it('logs out an authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect(route('home'));

    $this->assertGuest();
});
