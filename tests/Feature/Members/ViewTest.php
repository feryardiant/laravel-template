<?php

declare(strict_types=1);

use App\Enums\ContactDetailType;
use App\Enums\Gender;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use App\Support\Feature\ContactGender;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

test('a member can be viewed by uuid', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->asMemberOf($organization)->create(['name' => 'Viewable Member']);

    get(route('members.show', $member->person))
        ->assertOk()
        ->assertSee('Viewable Member');
});

test('a member from another organization is not found', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $outside = User::factory()->asMemberOf(Organization::factory())->create();

    get(route('members.show', $outside->person))->assertNotFound();
});

test('the member detail page shows the membership role', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->asMemberOf($organization, Role::Admin)->create(['name' => 'Detail Member']);

    get(route('members.show', $member->person))
        ->assertOk()
        ->assertSee('Detail Member')
        ->assertSee(__('Admin'));
});

test('the member view shows gender when the workspace enables it', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    ContactGender::activate($organization);

    $member = User::factory()->asMemberOf($organization, Role::Member)->create();
    $member->person->update(['gender' => Gender::Female]);

    get(route('members.show', $member->person))
        ->assertOk()
        ->assertSee(__('Gender'))
        ->assertSee(__('Female'));
});

test('the member view displays contact details', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $member = User::factory()->asMemberOf($organization)->create(['name' => 'Contactable Member']);
    $member->person->contactDetails()->createMany([
        ['type' => ContactDetailType::Email, 'value' => 'reach@example.test'],
        ['type' => ContactDetailType::Phone, 'value' => '0812 3456'],
    ]);

    get(route('members.show', $member->person))
        ->assertOk()
        ->assertSee('reach@example.test')
        ->assertSee('0812 3456');
});
