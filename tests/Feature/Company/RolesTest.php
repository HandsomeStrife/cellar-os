<?php

declare(strict_types=1);

use App\Livewire\Billing\Pricing;
use App\Livewire\Inventory\Index as InventoryIndex;
use Domain\Billing\Enums\Plan;
use Domain\User\Enums\Role;
use Domain\Venue\Models\Venue;
use Livewire\Livewire;

it('lets only an owner change the plan', function () {
    config(['cashier.secret' => 'sk_test', 'billing.prices.starter' => 'price_starter']);
    [$company, $manager] = makeTenant(Plan::Pro, Role::Manager);
    $this->actingAs($manager);

    Livewire::test(Pricing::class)->call('checkout', 'starter')->assertForbidden();
});

it('scopes a member to only their assigned venues', function () {
    [$company, $member, $assigned] = makeTenant(Plan::Group, Role::Member);
    // A second venue the member is NOT assigned to.
    $other = Venue::factory()->create(['company_id' => $company->id, 'name' => 'Hidden Cellar']);
    $assigned->update(['name' => 'My Cellar']);

    $this->actingAs($member);

    Livewire::test(InventoryIndex::class)
        ->assertSee('My Cellar')
        ->assertDontSee('Hidden Cellar');
});

it('gives an owner access to every company venue', function () {
    [$company, $owner, $first] = makeTenant(Plan::Group);
    Venue::factory()->create(['company_id' => $company->id, 'name' => 'Second Cellar']);
    $first->update(['name' => 'First Cellar']);

    $this->actingAs($owner);

    Livewire::test(InventoryIndex::class)
        ->assertSee('First Cellar')
        ->assertSee('Second Cellar');
});
