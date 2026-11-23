<?php

declare(strict_types=1);

use App\Actions\Contacts\CreateContactOrganization;
use App\Enums\Gender;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates the organization and its primary contact', function () {
    $organization = app(CreateContactOrganization::class)->handle(
        name: 'Acme',
        contactName: 'Jane Doe',
        summary: 'Summary',
        logoPath: 'logos/acme.png',
        contactGender: Gender::Female,
        contactSummary: 'Contact summary',
    );

    expect($organization->name)->toBe('Acme')
        ->and($organization->summary)->toBe('Summary')
        ->and($organization->getRawOriginal('logo_url'))->toBe('logos/acme.png')
        ->and($organization->primaryContact?->name)->toBe('Jane Doe')
        ->and($organization->primaryContact?->gender)->toBe(Gender::Female)
        ->and($organization->primaryContact?->summary)->toBe('Contact summary');
});

it('assigns the owner to the organization and its contact', function () {
    $owner = Organization::factory()->create();

    $organization = app(CreateContactOrganization::class)->handle(
        name: 'Acme',
        contactName: 'Jane Doe',
        owner: $owner,
    );

    expect($organization->owner_organization_id)->toBe($owner->getKey())
        ->and($organization->primaryContact?->owner_organization_id)->toBe($owner->getKey());
});

it('reuses the given user for the primary contact', function () {
    $user = User::factory()->create();

    $organization = app(CreateContactOrganization::class)->handle(
        name: 'Acme',
        contactName: 'Jane Doe',
        user: $user,
    );

    expect($organization->primaryContact?->user_id)->toBe($user->getKey());
});
