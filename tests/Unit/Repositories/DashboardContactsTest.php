<?php

declare(strict_types=1);

use App\Enums\OrganizationRelationshipType;
use App\Models\Organization;
use App\Models\Person;
use App\Repositories\ContactRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('counts and lists new and expiring contacts', function () {
    $organization = Organization::factory()->create();
    $person = Person::factory()->withoutCredential()->create();

    $organization->personCounterparties(OrganizationRelationshipType::Customer)->attach($person, [
        'type' => OrganizationRelationshipType::Customer,
        'start_date' => today(),
        'finish_date' => today()->addDays(2),
    ]);

    $range = [
        'start' => today()->subDay()->toDateString(),
        'end' => today()->addDays(3)->toDateString(),
    ];

    $contacts = app(ContactRepository::class);

    expect($contacts->countRecent($organization, $range))->toBe(1)
        ->and($contacts->countExpiring($organization, $range))->toBe(1)
        ->and($contacts->getExpiring($organization, $range)->first()?->stakeholder?->is($person))->toBeTrue();
});
