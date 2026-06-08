<?php

declare(strict_types=1);

use App\Livewire\Admin\Suppliers;
use App\Livewire\Admin\SupplierShow;
use Domain\Admin\Models\Admin;
use Domain\Company\Models\Company;
use Domain\Supplier\Enums\SupplierTier;
use Domain\Supplier\Jobs\AnalyseSupplierDocumentJob;
use Domain\Supplier\Models\Supplier;
use Domain\Supplier\Models\SupplierDocument;
use Domain\Supplier\Models\SupplierUser;
use Domain\Supplier\Notifications\SupplierPasswordSetupNotification;
use Domain\User\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

it('lets an admin create a supplier', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');

    Livewire::test(Suppliers::class)
        ->call('create')
        ->set('name', 'Rhône Valley Wines')
        ->set('email', 'hello@rhone.test')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('suppliers', ['name' => 'Rhône Valley Wines']);
});

it('adds a supplier user and emails an invite', function () {
    Notification::fake();
    $this->actingAs(Admin::factory()->create(), 'admin');
    $supplier = Supplier::factory()->create();

    Livewire::test(SupplierShow::class, ['uuid' => $supplier->uuid])
        ->set('newUserName', 'Jane Vine')
        ->set('newUserEmail', 'jane@vine.test')
        ->call('addUser')
        ->assertHasNoErrors();

    $user = SupplierUser::where('email', 'jane@vine.test')->first();
    expect($user)->not->toBeNull()
        ->and($user->supplier_id)->toBe($supplier->id)
        ->and($user->password)->toBeNull();

    Notification::assertSentTo($user, SupplierPasswordSetupNotification::class);
});

it('queues analysis for a document', function () {
    Bus::fake();
    $this->actingAs(Admin::factory()->create(), 'admin');
    $supplier = Supplier::factory()->create();
    $document = SupplierDocument::factory()->create(['supplier_id' => $supplier->id]);

    Livewire::test(SupplierShow::class, ['uuid' => $supplier->uuid])
        ->call('analyse', $document->id);

    Bus::assertDispatched(AnalyseSupplierDocumentJob::class, fn ($job) => $job->documentId === $document->id);
});

it('updates a supplier profile', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
    $supplier = Supplier::factory()->create();

    Livewire::test(SupplierShow::class, ['uuid' => $supplier->uuid])
        ->set('website', 'https://updated.test')
        ->set('city', 'Reims')
        ->call('saveProfile')
        ->assertHasNoErrors();

    expect($supplier->fresh()->website)->toBe('https://updated.test')
        ->and($supplier->fresh()->city)->toBe('Reims');
});

it('promotes a private supplier to public, then onboarded', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
    $company = Company::factory()->create();
    $supplier = Supplier::factory()->create(['created_by_company_id' => $company->id]);

    expect($supplier->fresh()->getData()->tier)->toBe(SupplierTier::Private);

    Livewire::test(SupplierShow::class, ['uuid' => $supplier->uuid])->call('makePublic');
    expect($supplier->fresh()->getData()->tier)->toBe(SupplierTier::Listed);

    Livewire::test(SupplierShow::class, ['uuid' => $supplier->uuid])->call('markOnboarded');
    expect($supplier->fresh()->getData()->tier)->toBe(SupplierTier::Onboarded);
});

it('forbids a non-admin from supplier management actions', function () {
    $this->actingAs(User::factory()->create()); // web guard only
    $supplier = Supplier::factory()->create();

    Livewire::test(SupplierShow::class, ['uuid' => $supplier->uuid])
        ->set('newUserName', 'X')
        ->set('newUserEmail', 'x@x.test')
        ->call('addUser')
        ->assertForbidden();

    $this->assertDatabaseMissing('supplier_users', ['email' => 'x@x.test']);
});
