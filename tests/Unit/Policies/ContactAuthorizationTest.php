<?php

declare(strict_types=1);

use App\Actions\Tenancies\ToggleFeature;
use App\Enums\Feature;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

test('owners and admins can manage contacts', function (Role $role) {
    $organization = Organization::factory()->create();

    actingAsMember($organization, $role);

    expect(Gate::allows('createContact', $organization))->toBeTrue()
        ->and(Gate::allows('deleteAnyContact', $organization))->toBeTrue();
})->with([Role::Owner, Role::Admin]);

test('plain members cannot manage contacts', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Member);

    expect(Gate::allows('createContact', $organization))->toBeFalse()
        ->and(Gate::allows('deleteAnyContact', $organization))->toBeFalse();
});

test('contact entity abilities are identical for both stakeholder models', function (string $kind) {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $entity = $kind === 'organization'
        ? contactOrganization($organization, ['name' => 'Managed'])
        : contactIndividual($organization, ['name' => 'Managed']);

    expect(Gate::allows('viewContact', [$entity, $organization]))->toBeTrue()
        ->and(Gate::allows('updateContact', [$entity, $organization]))->toBeTrue()
        ->and(Gate::allows('deleteContact', [$entity, $organization]))->toBeTrue();
})->with(['organization', 'individual']);

test('members can view their organization contacts', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Member);

    $contact = contactIndividual($organization, ['name' => 'Viewable']);

    expect(Gate::allows('viewContact', [$contact, $organization]))->toBeTrue()
        ->and(Gate::allows('updateContact', [$contact, $organization]))->toBeFalse()
        ->and(Gate::allows('deleteContact', [$contact, $organization]))->toBeFalse();
});

test('owners cannot manage contacts of another organization', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $foreign = contactIndividual(Organization::factory()->create(), ['name' => 'Foreign']);

    expect(Gate::allows('viewContact', [$foreign, $organization]))->toBeFalse()
        ->and(Gate::allows('updateContact', [$foreign, $organization]))->toBeFalse()
        ->and(Gate::allows('deleteContact', [$foreign, $organization]))->toBeFalse();
});

test('a non-counterparty is not a viewable contact', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Member);

    $stranger = Person::factory()->create();

    expect(Gate::allows('viewContact', [$stranger, $organization]))->toBeFalse();
});

test('a deleted organization contact stays viewable but not editable', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $contact = contactOrganization($organization);

    $contact->delete();

    expect(Gate::allows('viewContact', [$contact, $organization]))->toBeTrue()
        ->and(Gate::allows('updateContact', [$contact, $organization]))->toBeFalse();
});

test('contact deletion follows the tenant feature flag', function () {
    $organization = Organization::factory()->create();

    actingAsMember($organization, Role::Owner);

    $contact = contactIndividual($organization);

    app(ToggleFeature::class)->deactivate($organization, Feature::ContactDeletion);

    expect(Gate::allows('deleteContact', [$contact, $organization]))->toBeFalse()
        ->and(Gate::allows('deleteAnyContact', $organization))->toBeFalse();
});
