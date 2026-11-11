<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

test('owners and admins can manage contacts', function (Role $role) {
    $organization = Organization::factory()->create();

    actingAsMember($organization, $role);

    expect(Gate::allows('createContact', Organization::class))->toBeTrue();
    expect(Gate::allows('deleteAnyContact', Organization::class))->toBeTrue();
})->with([Role::Owner, Role::Admin]);

test('plain members cannot manage contacts', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Member);

    expect(Gate::allows('createContact', Organization::class))->toBeFalse();
    expect(Gate::allows('deleteAnyContact', Organization::class))->toBeFalse();
});

test('members can view their organization contacts', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Member);

    $contact = contactIndividual($organization, ['name' => 'Viewable']);

    expect(Gate::allows('viewAnyContact', Organization::class))->toBeTrue();
    expect(Gate::allows('viewContact', $contact))->toBeTrue();
    expect(Gate::allows('updateContact', $contact))->toBeFalse();
    expect(Gate::allows('deleteContact', $contact))->toBeFalse();
});

test('owners cannot manage contacts of another organization', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $foreign = contactIndividual(Organization::factory()->create(), ['name' => 'Foreign']);

    expect(Gate::allows('viewContact', $foreign))->toBeFalse();
    expect(Gate::allows('updateContact', $foreign))->toBeFalse();
    expect(Gate::allows('deleteContact', $foreign))->toBeFalse();
});

test('a user without an organization cannot manage contacts', function () {
    $user = User::factory()->create();

    test()->actingAs($user);

    expect(Gate::allows('viewAnyContact', Organization::class))->toBeTrue();
    expect(Gate::allows('createContact', Organization::class))->toBeFalse();
    expect(Gate::allows('deleteAnyContact', Organization::class))->toBeFalse();
});
