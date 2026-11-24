<?php

declare(strict_types=1);

use App\Actions\Memberships\CreateMember;
use App\Actions\Relationships\RelationshipTimeline;
use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use App\Repositories\RelationshipRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists membership and contact periods', function () {
    $organization = Organization::factory()->create();
    $member = Person::factory()->withoutCredential()->create();
    app(CreateMember::class)->attach($organization, $member, startDate: now(), role: Role::Member);

    $contact = OrganizationRelationship::create([
        'organization_id' => $organization->getKey(),
        'stakeholder_type' => (new Person)->getMorphClass(),
        'stakeholder_id' => Person::factory()->withoutCredential()->create()->getKey(),
        'type' => OrganizationRelationshipType::Customer,
        'start_date' => now(),
    ]);
    app(RelationshipTimeline::class)->open($contact);

    $relationships = app(RelationshipRepository::class);

    expect($relationships->getForMember($organization, $member))->toHaveCount(1)
        ->and($relationships->getForContact($contact))->toHaveCount(1);
});
