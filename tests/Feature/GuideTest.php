<?php

declare(strict_types=1);

use App\Livewire\Guide;
use Domain\User\Models\User;

it('renders the guide index (welcome) for guests', function () {
    $this->get(route('guide'))
        ->assertOk()
        ->assertSeeLivewire(Guide::class)
        ->assertSee('Welcome')
        ->assertSee('Using CellarOS')   // sidenav group
        ->assertSee('quick start');
});

it('renders a specific section as its own URL', function () {
    $this->get(route('guide.section', 'catalogue'))
        ->assertOk()
        ->assertSee('The order basket')   // catalogue section content
        ->assertSee('Purchase orders');   // sidenav link
});

it('lists the demo logins with credentials', function () {
    $this->get(route('guide.section', 'demo-logins'))
        ->assertOk()
        ->assertSee('Demo logins')
        ->assertSee('demo@cellaros.test')
        ->assertSee('admin@cellaros.test')
        ->assertSee('password');
});

it('renders the plan matrix section', function () {
    $this->get(route('guide.section', 'plans'))
        ->assertOk()
        ->assertSee('Feature matrix')
        ->assertSee('Import supplier price lists');
});

it('falls back to welcome for an unknown section (no 404)', function () {
    $this->get(route('guide.section', 'does-not-exist'))
        ->assertOk()
        ->assertSee('Welcome');
});

it('is reachable without authentication and shows sign-in chrome', function () {
    $this->get(route('guide.section', 'orders'))
        ->assertOk()
        ->assertSee('Sign in');
});

it('shows the dashboard link in the header for authenticated users', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('guide'))->assertOk()->assertSee('Dashboard');
});

it('exposes a section for every sidenav entry', function () {
    foreach (Guide::sections() as $group) {
        foreach ($group['items'] as $slug => $entry) {
            $this->get(route('guide.section', $slug))
                ->assertOk()
                ->assertSee($entry['title']);
        }
    }
});
