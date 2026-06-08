<?php

declare(strict_types=1);

use App\Livewire\Suppliers\Index;
use Domain\Company\Models\Company;
use Domain\Supplier\Actions\ConnectCompanyToSupplierAction;
use Domain\Supplier\Actions\SyncSupplierVenuesAction;
use Domain\Supplier\Models\Supplier;
use Domain\User\Models\User;
use Domain\Venue\Models\Venue;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function connectCompanySupplier(int $companyId, int $supplierId): void
{
    (new ConnectCompanyToSupplierAction)->execute($companyId, $supplierId);
}

it('renders the suppliers page', function () {
    $this->get(route('suppliers'))->assertOk()->assertSeeLivewire(Index::class);
});

it('lists the company\'s connected suppliers under My suppliers', function () {
    $supplier = Supplier::factory()->create(['name' => 'Connected Co']);
    connectCompanySupplier($this->user->company_id, $supplier->id);

    Livewire::test(Index::class)->assertSee('Connected Co');
});

it('does not show suppliers the company is not connected to', function () {
    Supplier::factory()->create(['name' => 'Stranger Co']);

    Livewire::test(Index::class)->assertDontSee('Stranger Co');
});

it('adds a private supplier connected to your company', function () {
    Livewire::test(Index::class)
        ->call('create')
        ->set('name', 'My Merchant')
        ->set('email', 'hi@merchant.test')
        ->call('save')
        ->assertHasNoErrors();

    $supplier = Supplier::where('name', 'My Merchant')->firstOrFail();
    expect($supplier->created_by_company_id)->toBe($this->user->company_id);
    $this->assertDatabaseHas('company_supplier', [
        'company_id' => $this->user->company_id,
        'supplier_id' => $supplier->id,
    ]);
});

it('requires a name', function () {
    Livewire::test(Index::class)
        ->call('create')->set('name', '')->call('save')
        ->assertHasErrors(['name' => 'required']);
});

it('validates the email format', function () {
    Livewire::test(Index::class)
        ->call('create')->set('name', 'Bad Email Co')->set('email', 'nope')->call('save')
        ->assertHasErrors(['email' => 'email']);
});

it('connects to a listed supplier from discovery', function () {
    $supplier = Supplier::factory()->create(['name' => 'Listed Co']); // public (created_by null)

    Livewire::test(Index::class)->call('connect', $supplier->id);

    $this->assertDatabaseHas('company_supplier', [
        'company_id' => $this->user->company_id,
        'supplier_id' => $supplier->id,
    ]);
});

it('edits its own private supplier', function () {
    $supplier = Supplier::factory()->create([
        'name' => 'Old Name',
        'created_by_company_id' => $this->user->company_id,
    ]);

    Livewire::test(Index::class)
        ->call('edit', $supplier->id)
        ->assertSet('name', 'Old Name')
        ->set('name', 'New Name')
        ->call('save')
        ->assertHasNoErrors();

    expect($supplier->fresh()->name)->toBe('New Name');
});

it('forbids editing a public supplier', function () {
    $supplier = Supplier::factory()->create(); // public, not owned

    Livewire::test(Index::class)->call('edit', $supplier->id)->assertForbidden();
});

it('forbids editing another company\'s private supplier', function () {
    $other = Company::factory()->create();
    $supplier = Supplier::factory()->create(['created_by_company_id' => $other->id]);

    Livewire::test(Index::class)->call('edit', $supplier->id)->assertForbidden();
});

it('deletes its own private supplier', function () {
    $supplier = Supplier::factory()->create(['created_by_company_id' => $this->user->company_id]);

    Livewire::test(Index::class)->call('delete', $supplier->id);

    $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
});

it('forbids deleting a public supplier', function () {
    $supplier = Supplier::factory()->create();

    Livewire::test(Index::class)->call('delete', $supplier->id)->assertForbidden();

    $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
});

it('disconnects a connected supplier without deleting the shared record', function () {
    $supplier = Supplier::factory()->create();
    connectCompanySupplier($this->user->company_id, $supplier->id);

    Livewire::test(Index::class)->call('disconnect', $supplier->id);

    $this->assertDatabaseMissing('company_supplier', [
        'company_id' => $this->user->company_id,
        'supplier_id' => $supplier->id,
    ]);
    $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
});

it('forbids connecting to a private supplier', function () {
    $other = Company::factory()->create();
    $supplier = Supplier::factory()->create(['created_by_company_id' => $other->id]);

    Livewire::test(Index::class)->call('connect', $supplier->id)->assertForbidden();

    $this->assertDatabaseMissing('company_supplier', [
        'company_id' => $this->user->company_id,
        'supplier_id' => $supplier->id,
    ]);
});

it('forbids disconnecting a supplier you are not connected to', function () {
    $supplier = Supplier::factory()->create();

    Livewire::test(Index::class)->call('disconnect', $supplier->id)->assertForbidden();
});

it('forbids allocating venues for a supplier you are not connected to', function () {
    $supplier = Supplier::factory()->create();

    Livewire::test(Index::class)->call('startAllocate', $supplier->id)->assertForbidden();
});

it('allocates a connected supplier to a venue', function () {
    [$company, $user, $venue] = makeTenant();
    $this->actingAs($user);
    $supplier = Supplier::factory()->create();
    connectCompanySupplier($company->id, $supplier->id);

    Livewire::test(Index::class)
        ->call('startAllocate', $supplier->id)
        ->set('allocVenueIds', [$venue->id])
        ->call('saveAllocation')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('supplier_venue', [
        'supplier_id' => $supplier->id,
        'venue_id' => $venue->id,
    ]);
});

it('does not disturb another company\'s allocations of the same shared supplier', function () {
    $supplier = Supplier::factory()->create();
    [$companyA, $userA, $venueA] = makeTenant();
    [$companyB, $userB, $venueB] = makeTenant();

    // B allocates the supplier to its own venue.
    (new SyncSupplierVenuesAction)->execute($supplier->id, [$venueB->id], [$venueB->id]);

    // A syncs its own venues (clears them) — must not touch B's allocation.
    (new SyncSupplierVenuesAction)->execute($supplier->id, [$venueA->id], []);

    $this->assertDatabaseHas('supplier_venue', ['supplier_id' => $supplier->id, 'venue_id' => $venueB->id]);
    $this->assertDatabaseMissing('supplier_venue', ['supplier_id' => $supplier->id, 'venue_id' => $venueA->id]);
});
