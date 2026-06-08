<?php

declare(strict_types=1);

use Domain\Enquiry\Actions\StoreEnquiryAction;
use Domain\Enquiry\Data\EnquiryData;
use Domain\Enquiry\Enums\EnquiryStatus;
use Domain\Enquiry\Models\Enquiry;

it('shows the contact form on the landing page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Get in touch')
        ->assertSee('Send enquiry');
});

it('stores a submitted enquiry and redirects with success', function () {
    $response = $this->post(route('enquiries.store'), [
        'name' => 'Jane Merchant',
        'email' => 'jane@example.test',
        'company' => 'Merchant Wines',
        'message' => 'We would like a demo for our import business.',
    ]);

    $response->assertRedirect(route('home').'#contact')
        ->assertSessionHas('enquiry_success');

    $this->assertDatabaseHas('enquiries', [
        'name' => 'Jane Merchant',
        'email' => 'jane@example.test',
        'company' => 'Merchant Wines',
        'status' => EnquiryStatus::New->value,
    ]);
});

it('stores an enquiry without a company', function () {
    $this->post(route('enquiries.store'), [
        'name' => 'No Company',
        'email' => 'solo@example.test',
        'message' => 'Just me.',
    ])->assertSessionHas('enquiry_success');

    $this->assertDatabaseHas('enquiries', ['email' => 'solo@example.test', 'company' => null]);
});

it('validates required fields', function () {
    $this->post(route('enquiries.store'), [
        'name' => '',
        'email' => 'not-an-email',
        'message' => '',
    ])->assertSessionHasErrors(['name', 'email', 'message']);

    expect(Enquiry::count())->toBe(0);
});

it('defaults a stored enquiry to the New status via the action', function () {
    $data = (new StoreEnquiryAction)->execute(new EnquiryData(
        id: null,
        uuid: null,
        name: 'Action Test',
        email: 'action@example.test',
        company: null,
        message: 'Hello',
    ));

    expect($data->status)->toBe(EnquiryStatus::New)
        ->and($data->uuid)->not->toBeNull();
});
