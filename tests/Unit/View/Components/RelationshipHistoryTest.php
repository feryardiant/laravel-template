<?php

declare(strict_types=1);

use App\Enums\OrganizationRelationshipType;
use App\Models\OrganizationRelationshipPeriod;
use Tests\TestCase;

function relationshipPeriod(array $attributes = []): OrganizationRelationshipPeriod
{
    return new OrganizationRelationshipPeriod([
        'type' => OrganizationRelationshipType::Customer,
        'start_date' => '2026-01-01',
        'finish_date' => '2026-02-01',
        'closed_at' => '2026-02-01 00:00:00',
        ...$attributes,
    ]);
}

it('sets the timeline status from the period state rather than its position', function () {
    /** @var TestCase $this */
    $this->blade('<x-relationship-history :periods="$periods" />', [
        'periods' => collect([
            relationshipPeriod(),
            relationshipPeriod(['start_date' => '2026-03-01', 'finish_date' => null, 'closed_at' => null]),
        ]),
    ])
        ->assertSeeInOrder(['data-status="complete"', 'data-status="current"'], false);
});

it('marks every closed period as complete', function () {
    /** @var TestCase $this */
    $this->blade('<x-relationship-history :periods="$periods" />', [
        'periods' => collect([relationshipPeriod()]),
    ])
        ->assertSee('data-status="complete"', false)
        ->assertDontSee('aria-current="step"', false);
});
