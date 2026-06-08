<?php

declare(strict_types=1);

use App\Livewire\Admin\Enquiries;
use Domain\Admin\Models\Admin;
use Domain\Enquiry\Enums\EnquiryStatus;
use Domain\Enquiry\Models\Enquiry;
use Domain\User\Models\User;
use Livewire\Livewire;

it('redirects guests from the admin enquiries page to the admin login', function () {
    $this->get(route('admin.enquiries'))->assertRedirect(route('admin.login'));
});

it('lists enquiries for an admin', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
    Enquiry::factory()->create(['email' => 'lead@example.test']);

    $this->get(route('admin.enquiries'))->assertOk()->assertSee('lead@example.test');
});

it('marks an enquiry as read and stamps handled_at', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
    $enquiry = Enquiry::factory()->create(['status' => EnquiryStatus::New->value]);

    Livewire::test(Enquiries::class)->call('mark', $enquiry->uuid, EnquiryStatus::Read->value);

    $fresh = $enquiry->fresh();
    expect($fresh->status)->toBe(EnquiryStatus::Read)
        ->and($fresh->handled_at)->not->toBeNull();
});

it('filters enquiries by status', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
    Enquiry::factory()->create(['status' => EnquiryStatus::New->value, 'name' => 'Fresh Lead']);
    Enquiry::factory()->create(['status' => EnquiryStatus::Archived->value, 'name' => 'Old Lead']);

    Livewire::test(Enquiries::class)
        ->set('status', EnquiryStatus::New->value)
        ->assertSee('Fresh Lead')
        ->assertDontSee('Old Lead');
});

it('deletes an enquiry', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
    $enquiry = Enquiry::factory()->create();

    Livewire::test(Enquiries::class)->call('deleteEnquiry', $enquiry->uuid);

    $this->assertDatabaseMissing('enquiries', ['id' => $enquiry->id]);
});

it('forbids a non-admin from invoking enquiry actions', function () {
    $this->actingAs(User::factory()->create()); // web guard only
    $enquiry = Enquiry::factory()->create();

    Livewire::test(Enquiries::class)->call('deleteEnquiry', $enquiry->uuid)->assertForbidden();

    $this->assertDatabaseHas('enquiries', ['id' => $enquiry->id]);
});
