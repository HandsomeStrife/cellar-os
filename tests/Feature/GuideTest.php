<?php

declare(strict_types=1);

use App\Livewire\Guide;
use Domain\User\Models\User;

it('renders the guide with features and journeys', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('guide'))
        ->assertOk()
        ->assertSeeLivewire(Guide::class)
        ->assertSee('CellarOS guide')
        ->assertSee('User journey')
        ->assertSee('What each plan unlocks');
});

it('is accessible to guests (not behind auth)', function () {
    $this->get(route('guide'))
        ->assertOk()
        ->assertSee('CellarOS guide')
        ->assertSee('Sign in');
});
