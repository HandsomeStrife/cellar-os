<?php

declare(strict_types=1);

use App\Livewire\Inventory\Index;
use Domain\Billing\Enums\Plan;
use Domain\Catalogue\Models\Product;
use Domain\Inventory\Models\InventoryAttachment;
use Domain\Inventory\Models\InventoryItem;
use Domain\Supplier\Models\Supplier;
use Domain\User\Models\User;
use Domain\Venue\Models\Venue;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = userOnPlan(Plan::Pro);
    $this->venue = Venue::factory()->create(['company_id' => $this->user->company_id]);
    $supplier = Supplier::factory()->create();
    $this->product = Product::factory()->create(['supplier_id' => $supplier->id, 'wine_name' => 'Test Wine']);
    $this->actingAs($this->user);
});

it('renders the inventory page', function () {
    $this->get(route('inventory'))
        ->assertOk()
        ->assertSeeLivewire(Index::class);
});

it('shows an upgrade gate for free users', function () {
    $this->actingAs(userOnPlan(Plan::Free));

    Livewire::test(Index::class)->assertSee('Inventory is a paid feature');
});

it('lists stock for the active venue', function () {
    InventoryItem::factory()->create([
        'venue_id' => $this->venue->id,
        'product_id' => $this->product->id,
        'quantity_units' => 24,
    ]);

    Livewire::test(Index::class)
        ->assertSee('Test Wine')
        ->assertSee('24');
});

it('creates a venue', function () {
    $user = userOnPlan(Plan::Starter);
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('venueName', 'My Cellar')
        ->call('createVenue')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('venues', ['name' => 'My Cellar', 'company_id' => $user->company_id]);
});

it('receives stock manually', function () {
    Livewire::test(Index::class)
        ->set('addProductId', $this->product->id)
        ->set('addQuantity', 12)
        ->set('addPrice', '20.00')
        ->call('saveItem')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('inventory_items', [
        'venue_id' => $this->venue->id,
        'product_id' => $this->product->id,
        'quantity_units' => 12,
    ]);
});

it('tops up an existing line instead of duplicating', function () {
    $item = InventoryItem::factory()->create([
        'venue_id' => $this->venue->id,
        'product_id' => $this->product->id,
        'quantity_units' => 6,
    ]);

    Livewire::test(Index::class)
        ->set('addProductId', $this->product->id)
        ->set('addQuantity', 6)
        ->call('saveItem');

    expect($item->fresh()->quantity_units)->toBe(12);
    $this->assertDatabaseCount('inventory_items', 1);
});

it('forbids manual add below the Pro plan', function () {
    $this->actingAs($starter = userOnPlan(Plan::Starter));
    Venue::factory()->create(['company_id' => $starter->company_id]);

    Livewire::test(Index::class)
        ->set('addProductId', $this->product->id)
        ->set('addQuantity', 1)
        ->call('saveItem')
        ->assertForbidden();

    $this->assertDatabaseCount('inventory_items', 0);
});

it('adjusts a quantity', function () {
    $item = InventoryItem::factory()->create([
        'venue_id' => $this->venue->id,
        'product_id' => $this->product->id,
        'quantity_units' => 10,
    ]);

    Livewire::test(Index::class)->call('adjustQuantity', $item->id, 7);

    expect($item->fresh()->quantity_units)->toBe(7);
});

it('archives and restores a line', function () {
    $item = InventoryItem::factory()->create([
        'venue_id' => $this->venue->id,
        'product_id' => $this->product->id,
        'is_archived' => false,
    ]);

    Livewire::test(Index::class)->call('archive', $item->id);
    expect($item->fresh()->is_archived)->toBeTrue();

    Livewire::test(Index::class)->set('showArchived', true)->call('restore', $item->id);
    expect($item->fresh()->is_archived)->toBeFalse();
});

it('forbids adjusting quantity for free users', function () {
    $free = userOnPlan(Plan::Free);
    $venue = Venue::factory()->create(['company_id' => $free->company_id]);
    $item = InventoryItem::factory()->create([
        'venue_id' => $venue->id,
        'product_id' => $this->product->id,
        'quantity_units' => 5,
    ]);
    $this->actingAs($free);

    Livewire::test(Index::class)->call('adjustQuantity', $item->id, 50)->assertForbidden();

    expect($item->fresh()->quantity_units)->toBe(5);
});

it('requires the Group plan to create a second venue', function () {
    // $this->user is Pro with one venue already.
    Livewire::test(Index::class)
        ->set('venueName', 'Second Cellar')
        ->call('createVenue')
        ->assertForbidden();

    expect(Venue::where('company_id', $this->user->company_id)->count())->toBe(1);
});

it('allows a Group user multiple venues', function () {
    $this->actingAs($group = userOnPlan(Plan::Group));
    Venue::factory()->create(['company_id' => $group->company_id]);

    Livewire::test(Index::class)
        ->set('venueName', 'Second Cellar')
        ->call('createVenue')
        ->assertHasNoErrors();

    expect(Venue::where('company_id', $group->company_id)->count())->toBe(2);
});

it('rejects a disallowed attachment type', function () {
    $item = InventoryItem::factory()->create([
        'venue_id' => $this->venue->id,
        'product_id' => $this->product->id,
    ]);

    Livewire::test(Index::class)
        ->call('openAttachments', $item->id)
        ->set('upload', UploadedFile::fake()->create('malware.exe', 50, 'application/x-msdownload'))
        ->call('uploadAttachment')
        ->assertHasErrors('upload');

    $this->assertDatabaseCount('inventory_attachments', 0);
});

it('un-archives a line when stock is received again', function () {
    $item = InventoryItem::factory()->create([
        'venue_id' => $this->venue->id,
        'product_id' => $this->product->id,
        'quantity_units' => 2,
        'is_archived' => true,
    ]);

    Livewire::test(Index::class)
        ->set('addProductId', $this->product->id)
        ->set('addQuantity', 6)
        ->call('saveItem');

    $fresh = $item->fresh();
    expect($fresh->is_archived)->toBeFalse()
        ->and($fresh->quantity_units)->toBe(8);
});

it('returns 404 downloading a missing attachment', function () {
    $this->get(route('inventory.attachments.download', 999999))->assertNotFound();
});

it('forbids touching another user\'s inventory', function () {
    $otherUser = userOnPlan(Plan::Pro);
    $otherVenue = Venue::factory()->create(['company_id' => $otherUser->company_id]);
    $otherItem = InventoryItem::factory()->create([
        'venue_id' => $otherVenue->id,
        'product_id' => $this->product->id,
        'quantity_units' => 5,
    ]);

    Livewire::test(Index::class)->call('adjustQuantity', $otherItem->id, 99)->assertForbidden();

    expect($otherItem->fresh()->quantity_units)->toBe(5);
});

it('uploads and downloads an attachment', function () {
    Storage::fake('local');

    $item = InventoryItem::factory()->create([
        'venue_id' => $this->venue->id,
        'product_id' => $this->product->id,
    ]);

    Livewire::test(Index::class)
        ->call('openAttachments', $item->id)
        ->set('upload', UploadedFile::fake()->create('invoice.pdf', 120, 'application/pdf'))
        ->call('uploadAttachment')
        ->assertHasNoErrors();

    $attachment = InventoryAttachment::first();
    expect($attachment)->not->toBeNull()
        ->and($attachment->file_name)->toBe('invoice.pdf');
    Storage::disk('local')->assertExists($attachment->storage_path);

    $this->get(route('inventory.attachments.download', $attachment->id))->assertOk();
});

it('forbids deleting another user\'s attachment', function () {
    $otherUser = userOnPlan(Plan::Pro);
    $otherVenue = Venue::factory()->create(['company_id' => $otherUser->company_id]);
    $otherItem = InventoryItem::factory()->create([
        'venue_id' => $otherVenue->id,
        'product_id' => $this->product->id,
    ]);
    $attachment = InventoryAttachment::factory()->create(['inventory_item_id' => $otherItem->id]);

    Livewire::test(Index::class)->call('deleteAttachment', $attachment->id)->assertForbidden();

    $this->assertDatabaseHas('inventory_attachments', ['id' => $attachment->id]);
});

it('forbids downloading another user\'s attachment', function () {
    $otherUser = userOnPlan(Plan::Pro);
    $otherVenue = Venue::factory()->create(['company_id' => $otherUser->company_id]);
    $otherItem = InventoryItem::factory()->create([
        'venue_id' => $otherVenue->id,
        'product_id' => $this->product->id,
    ]);
    $attachment = InventoryAttachment::factory()->create(['inventory_item_id' => $otherItem->id]);

    $this->get(route('inventory.attachments.download', $attachment->id))->assertForbidden();
});
