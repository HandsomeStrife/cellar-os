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

it('requires authentication for the guide', function () {
    $this->get(route('guide'))->assertRedirect(route('login'));
});
