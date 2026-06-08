<?php

declare(strict_types=1);

use App\Livewire\Auth\Register;
use Domain\Billing\Enums\Plan;
use Domain\User\Models\User;
use Domain\Venue\Models\Venue;
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
        'role' => 'owner',
    ]);

    // The registrant owns a brand-new company on the Free plan (the tenant).
    $user = User::where('email', 'ada@cellaros.test')->firstOrFail();
    $this->assertDatabaseHas('companies', [
        'id' => $user->company_id,
        'plan' => Plan::Free->value,
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

    $this->assertDatabaseHas('companies', [
        'id' => $user->company_id,
        'name' => 'The Vault',
        'base_currency' => 'EUR',
    ]);
    $this->assertDatabaseHas('venues', [
        'company_id' => $user->company_id,
        'name' => 'The Vault',
        'base_currency' => 'EUR',
    ]);
    // The owner is assigned to the first venue.
    $venue = Venue::where('company_id', $user->company_id)->firstOrFail();
    $this->assertDatabaseHas('user_venue', [
        'user_id' => $user->id,
        'venue_id' => $venue->id,
    ]);
    $this->assertDatabaseHas('user_profiles', [
        'user_id' => $user->id,
        'profession' => 'Sommelier',
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
