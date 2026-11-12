<?php

declare(strict_types=1);

use App\Enums\RelationshipStatus;
use App\Models\OrganizationRelationship;

test('the status is active inside the date window', function () {
    $relationship = new OrganizationRelationship;
    $relationship->start_date = today()->subDay();
    $relationship->finish_date = today()->addDay();

    expect($relationship->status)->toBe(RelationshipStatus::Active);
});

test('the status is scheduled before the start date', function () {
    $relationship = new OrganizationRelationship;
    $relationship->start_date = today()->addDay();

    expect($relationship->status)->toBe(RelationshipStatus::Scheduled);
});

test('the status is expired after the finish date', function () {
    $relationship = new OrganizationRelationship;
    $relationship->finish_date = today()->subDay();

    expect($relationship->status)->toBe(RelationshipStatus::Expired);
});

test('the status is active without dates', function () {
    expect((new OrganizationRelationship)->status)->toBe(RelationshipStatus::Active);
});

test('the current day is still active on both boundaries', function () {
    $relationship = new OrganizationRelationship;
    $relationship->start_date = today();
    $relationship->finish_date = today();

    expect($relationship->status)->toBe(RelationshipStatus::Active);
});
