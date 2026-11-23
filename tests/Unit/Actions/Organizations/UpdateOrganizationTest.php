<?php

declare(strict_types=1);

use App\Actions\Organizations\UpdateOrganization;
use App\Enums\ContactDetailType;
use App\Enums\Role;
use App\Events\Organizations\OrganizationUpdated;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('updates the organization profile and contact details', function () {
    Event::fake([OrganizationUpdated::class]);

    $organization = Organization::factory()->create(['name' => 'Old Name', 'summary' => 'Old summary']);
    $actor = User::factory()->asMemberOf($organization, Role::Owner)->create();

    $result = app(UpdateOrganization::class)->handle(
        organization: $organization,
        actor: $actor,
        data: [
            'name' => 'New Name',
            'summary' => '',
            'address' => null,
            'details' => [ContactDetailType::Email->value => 'hello@acme.test'],
            'remove_logo' => false,
        ],
        expected: [
            'name' => 'Old Name',
            'summary' => 'Old summary',
            'address' => null,
            'details' => [],
            'logo_url' => null,
        ],
    );

    expect($result['organization']->name)->toBe('New Name')
        ->and((string) $result['organization']->summary)->toBe('')
        ->and($result['organization']->contactDetailValue(ContactDetailType::Email))->toBe('hello@acme.test')
        ->and($result['logo_removal_failed'])->toBeFalse();

    Event::assertDispatched(
        OrganizationUpdated::class,
        fn (OrganizationUpdated $event): bool => $event->organizationId === $organization->getKey()
            && $event->payload->toArray() === ['fields' => ['name', 'summary', 'email']],
    );
});

it('keeps fields saved by another session', function () {
    $organization = Organization::factory()->create(['name' => 'Old Name', 'summary' => 'Old summary']);
    $actor = User::factory()->asMemberOf($organization, Role::Owner)->create();

    $organization->update(['name' => 'Newer Name']);

    app(UpdateOrganization::class)->handle(
        organization: $organization,
        actor: $actor,
        data: [
            'name' => 'Old Name',
            'summary' => 'Old summary',
            'address' => null,
            'details' => [],
            'remove_logo' => false,
        ],
        expected: [
            'name' => 'Old Name',
            'summary' => 'Old summary',
            'address' => null,
            'details' => [],
            'logo_url' => null,
        ],
    );

    expect($organization->fresh()->name)->toBe('Newer Name');
});
