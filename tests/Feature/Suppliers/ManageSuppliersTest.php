<?php

declare(strict_types=1);

use App\Livewire\Suppliers\Index;
use Domain\Supplier\Enums\SupplierStatus;
use Domain\Supplier\Models\Supplier;
use Domain\User\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('renders the suppliers page', function () {
    $this->get(route('suppliers'))
        ->assertOk()
        ->assertSeeLivewire(Index::class);
});

it('lists existing suppliers', function () {
    Supplier::factory()->create(['name' => 'Domaine Example']);

    Livewire::test(Index::class)->assertSee('Domaine Example');
});

it('creates a supplier', function () {
    Livewire::test(Index::class)
        ->call('create')
        ->set('name', 'New Vintner')
        ->set('contact', 'Jane Doe')
        ->set('email', 'hi@vintner.test')
        ->set('location', 'Bordeaux, France')
        ->set('status', SupplierStatus::Active->value)
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('suppliers', [
        'name' => 'New Vintner',
        'email' => 'hi@vintner.test',
        'status' => 'Active',
    ]);
});

it('requires a name', function () {
    Livewire::test(Index::class)
        ->call('create')
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name' => 'required']);
});

it('validates the email format', function () {
    Livewire::test(Index::class)
        ->call('create')
        ->set('name', 'Bad Email Co')
        ->set('email', 'not-an-email')
        ->call('save')
        ->assertHasErrors(['email' => 'email']);
});

it('updates an existing supplier', function () {
    $supplier = Supplier::factory()->create(['name' => 'Old Name']);

    Livewire::test(Index::class)
        ->call('edit', $supplier->id)
        ->assertSet('name', 'Old Name')
        ->set('name', 'Updated Name')
        ->call('save')
        ->assertHasNoErrors();

    expect($supplier->fresh()->name)->toBe('Updated Name');
});

it('deletes a supplier', function () {
    $supplier = Supplier::factory()->create();

    Livewire::test(Index::class)->call('delete', $supplier->id);

    $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
});

it('toggles supplier status', function () {
    $supplier = Supplier::factory()->create(['status' => SupplierStatus::Active->value]);

    Livewire::test(Index::class)->call('toggleStatus', $supplier->id);

    expect($supplier->fresh()->status)->toBe(SupplierStatus::Inactive);
});

it('filters suppliers by search term', function () {
    Supplier::factory()->create(['name' => 'Bordeaux Imports']);
    Supplier::factory()->create(['name' => 'Tuscany Wines']);

    Livewire::test(Index::class)
        ->set('search', 'Bordeaux')
        ->assertSee('Bordeaux Imports')
        ->assertDontSee('Tuscany Wines');
});
