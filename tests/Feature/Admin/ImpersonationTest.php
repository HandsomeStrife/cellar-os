<?php

declare(strict_types=1);

use Domain\Admin\Models\Admin;
use Domain\Supplier\Models\Supplier;
use Domain\Supplier\Models\SupplierUser;

it('lets an admin impersonate a buyer and land on their dashboard', function () {
    $admin = Admin::factory()->create();
    [, $user] = makeTenant();

    $this->actingAs($admin, 'admin')
        ->post(route('admin.impersonate.user', $user->id))
        ->assertRedirect(route('dashboard'));

    expect(auth('web')->id())->toBe($user->id)
        ->and(auth('admin')->id())->toBe($admin->id)   // admin stays signed in
        ->and(session('impersonator_admin_id'))->toBe($admin->id)
        ->and(session('impersonating_guard'))->toBe('web');

    // The impersonated app shell shows the banner.
    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('you are impersonating this account', false)
        ->assertSee($user->email);
});

it('lets an admin impersonate a supplier-portal user, including invite-pending ones', function () {
    $admin = Admin::factory()->create();
    $supplier = Supplier::factory()->create();
    $pending = SupplierUser::factory()->create(['supplier_id' => $supplier->id, 'password' => null]); // no password yet

    $this->actingAs($admin, 'admin')
        ->post(route('admin.impersonate.supplier-user', $pending->id))
        ->assertRedirect(route('supplier.dashboard'));

    expect(auth('supplier')->id())->toBe($pending->id)
        ->and(session('impersonating_guard'))->toBe('supplier');

    $this->get(route('supplier.dashboard'))
        ->assertOk()
        ->assertSee('you are impersonating this account', false);
});

it('returns to the admin console when impersonation stops', function () {
    $admin = Admin::factory()->create();
    [, $user] = makeTenant();

    $this->actingAs($admin, 'admin')->post(route('admin.impersonate.user', $user->id));
    $this->post(route('admin.impersonate.stop'));

    expect(auth('web')->check())->toBeFalse()          // buyer session ended
        ->and(auth('admin')->id())->toBe($admin->id)   // admin survives
        ->and(session()->has('impersonator_admin_id'))->toBeFalse();
});

it('forbids non-admins from impersonating', function () {
    [, $user] = makeTenant();
    $target = SupplierUser::factory()->create(['supplier_id' => Supplier::factory()->create()->id]);

    // Guest → redirected to admin login by the auth:admin middleware.
    $this->post(route('admin.impersonate.user', $user->id))->assertRedirect();

    // A regular signed-in buyer is no better.
    $this->actingAs($user)
        ->post(route('admin.impersonate.supplier-user', $target->id))
        ->assertRedirect();

    expect(auth('supplier')->check())->toBeFalse();
});

it('never shows the banner without an active impersonation', function () {
    [, $user] = makeTenant();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('you are impersonating this account', false);
});
