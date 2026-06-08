<?php

declare(strict_types=1);

use App\Livewire\Auth\Register;
use Domain\Billing\Enums\Plan;
use Domain\User\Models\User;
use Livewire\Livewire;

it('renders the registration screen', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertSeeLivewire(Register::class);
});

it('registers a new user on the free plan and signs them in', function () {
    Livewire::test(Register::class)
        ->set('full_name', 'Ada Vintner')
        ->set('email', 'ada@cellaros.test')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', [
        'email' => 'ada@cellaros.test',
        'full_name' => 'Ada Vintner',
        'plan' => Plan::Free->value,
        'role' => 'user',
    ]);
});

it('creates a venue and profile from the signup details', function () {
    Livewire::test(Register::class)
        ->set('full_name', 'Ada Vintner')
        ->set('email', 'ada3@cellaros.test')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->set('company_name', 'The Vault')
        ->set('profession', 'Sommelier')
        ->set('base_currency', 'EUR')
        ->call('register')
        ->assertHasNoErrors();

    $user = User::where('email', 'ada3@cellaros.test')->firstOrFail();

    $this->assertDatabaseHas('venues', [
        'user_id' => $user->id,
        'name' => 'The Vault',
        'base_currency' => 'EUR',
    ]);
    $this->assertDatabaseHas('user_profiles', [
        'user_id' => $user->id,
        'profession' => 'Sommelier',
        'company_name' => 'The Vault',
    ]);
});

it('requires a matching password confirmation', function () {
    Livewire::test(Register::class)
        ->set('full_name', 'Ada Vintner')
        ->set('email', 'ada2@cellaros.test')
        ->set('password', 'password123')
        ->set('password_confirmation', 'mismatch')
        ->call('register')
        ->assertHasErrors('password');

    $this->assertGuest();
});
