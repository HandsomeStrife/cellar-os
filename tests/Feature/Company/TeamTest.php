<?php

declare(strict_types=1);

use App\Livewire\Company\Team;
use Domain\Billing\Enums\Plan;
use Domain\User\Enums\Role;
use Domain\User\Models\User;
use Domain\User\Notifications\UserInviteNotification;
use Domain\Venue\Models\Venue;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

it('lets an owner invite a member and emails a setup link', function () {
    Notification::fake();
    [$company, $owner, $venue] = makeTenant(Plan::Group);
    $this->actingAs($owner);

    Livewire::test(Team::class)
        ->set('name', 'Sam Server')
        ->set('email', 'sam@team.test')
        ->set('role', Role::Member->value)
        ->set('venueIds', [$venue->id])
        ->call('invite')
        ->assertHasNoErrors();

    $member = User::where('email', 'sam@team.test')->first();
    expect($member)->not->toBeNull()
        ->and($member->company_id)->toBe($company->id)
        ->and($member->role)->toBe(Role::Member)
        ->and($member->password)->toBeNull();

    $this->assertDatabaseHas('user_venue', ['user_id' => $member->id, 'venue_id' => $venue->id]);
    Notification::assertSentTo($member, UserInviteNotification::class);
});

it('forbids assigning a role above your own', function () {
    [$company, $manager] = makeTenant(Plan::Group, Role::Manager);
    $this->actingAs($manager);

    Livewire::test(Team::class)
        ->set('name', 'Big Boss')
        ->set('email', 'boss@team.test')
        ->set('role', Role::Owner->value)
        ->call('invite')
        ->assertForbidden();

    $this->assertDatabaseMissing('users', ['email' => 'boss@team.test']);
});

it('keeps members out of the team area entirely', function () {
    [$company, $member] = makeTenant(Plan::Group, Role::Member);

    $this->actingAs($member)->get(route('team'))->assertForbidden();
});

it('only assigns pivot venues to members, not owners/managers', function () {
    Notification::fake();
    [$company, $owner, $venue] = makeTenant(Plan::Group);
    $this->actingAs($owner);

    // Invite a manager with venues selected — they should NOT get pivot rows
    // (managers see every venue implicitly).
    Livewire::test(Team::class)
        ->set('name', 'Manager Mae')
        ->set('email', 'mae@team.test')
        ->set('role', Role::Manager->value)
        ->set('venueIds', [$venue->id])
        ->call('invite')
        ->assertHasNoErrors();

    $manager = User::where('email', 'mae@team.test')->first();
    $this->assertDatabaseMissing('user_venue', ['user_id' => $manager->id]);
});

it('forbids removing yourself', function () {
    [$company, $owner] = makeTenant(Plan::Group);
    $this->actingAs($owner);

    Livewire::test(Team::class)->call('remove', $owner->id)->assertStatus(422);

    $this->assertDatabaseHas('users', ['id' => $owner->id]);
});

it('prevents the last owner from demoting themselves', function () {
    [$company, $owner] = makeTenant(Plan::Group);
    $this->actingAs($owner);

    Livewire::test(Team::class)
        ->call('startEdit', $owner->id)
        ->set('editRole', Role::Manager->value)
        ->call('saveEdit');

    expect($owner->fresh()->role)->toBe(Role::Owner);
});

it('allows demoting an owner when another owner remains', function () {
    [$company, $owner] = makeTenant(Plan::Group);
    $second = User::factory()->role(Role::Owner)->create(['company_id' => $company->id]);
    $this->actingAs($owner);

    Livewire::test(Team::class)
        ->call('startEdit', $second->id)
        ->set('editName', $second->full_name)
        ->set('editRole', Role::Manager->value)
        ->call('saveEdit')
        ->assertHasNoErrors();

    expect($second->fresh()->role)->toBe(Role::Manager);
});

it('forbids managing a user from another company', function () {
    [$companyA, $owner] = makeTenant(Plan::Group);
    [$companyB, $otherOwner] = makeTenant(Plan::Group);
    $this->actingAs($owner);

    Livewire::test(Team::class)->call('remove', $otherOwner->id)->assertForbidden();

    $this->assertDatabaseHas('users', ['id' => $otherOwner->id]);
});
