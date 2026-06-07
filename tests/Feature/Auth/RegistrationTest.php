<?php

declare(strict_types=1);

use App\Livewire\Auth\Register;
use Domain\Billing\Enums\Plan;
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
