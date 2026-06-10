<?php

declare(strict_types=1);

use App\Livewire\Admin\SupplierShow;
use Domain\Admin\Models\Admin;
use Domain\Supplier\Models\Supplier;
use Domain\Supplier\Models\SupplierNote;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = Admin::factory()->create();
    $this->actingAs($this->admin, 'admin');
    $this->supplier = Supplier::factory()->create();
});

it('adds and lists CRM notes against a supplier', function () {
    Livewire::test(SupplierShow::class, ['uuid' => $this->supplier->uuid])
        ->set('newNote', 'Spoke to sales — priced list arrives quarterly by email.')
        ->call('addNote')
        ->assertSee('priced list arrives quarterly');

    $note = SupplierNote::first();
    expect($note->supplier_id)->toBe($this->supplier->id)
        ->and($note->admin_id)->toBe($this->admin->id);
});

it('deletes a note, but never another supplier\'s note', function () {
    $own = SupplierNote::factory()->create(['supplier_id' => $this->supplier->id]);
    $other = SupplierNote::factory()->create(); // different supplier

    Livewire::test(SupplierShow::class, ['uuid' => $this->supplier->uuid])
        ->call('deleteNote', $own->id);
    $this->assertDatabaseMissing('supplier_notes', ['id' => $own->id]);

    Livewire::test(SupplierShow::class, ['uuid' => $this->supplier->uuid])
        ->call('deleteNote', $other->id)
        ->assertForbidden();
    $this->assertDatabaseHas('supplier_notes', ['id' => $other->id]);
});

it('round-trips CRM notes through the golden snapshot', function () {
    Storage::fake('local');
    $public = Supplier::factory()->create(['name' => 'Noted Imports']);
    SupplierNote::factory()->create(['supplier_id' => $public->id, 'note' => 'Research intel: list is quarterly.']);

    $this->artisan('wine:export-golden')->assertSuccessful();
    SupplierNote::query()->delete();
    Supplier::query()->delete();
    $this->artisan('wine:import-golden')->assertSuccessful();
    // Double import must not duplicate notes.
    $this->artisan('wine:import-golden')->assertSuccessful();

    $restored = Supplier::firstWhere('name', 'Noted Imports');
    expect(SupplierNote::where('supplier_id', $restored->id)->count())->toBe(1)
        ->and(SupplierNote::where('supplier_id', $restored->id)->value('note'))->toBe('Research intel: list is quarterly.');
});
