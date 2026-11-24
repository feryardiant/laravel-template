<?php

declare(strict_types=1);

use App\Actions\Contacts\EnsureExternalRole;
use App\Enums\OrganizationRelationshipType;

it('accepts an external role enum', function () {
    expect(app(EnsureExternalRole::class)->handle(OrganizationRelationshipType::Supplier))
        ->toBe(OrganizationRelationshipType::Supplier);
});

it('accepts an external role string', function () {
    expect(app(EnsureExternalRole::class)->handle('vendor'))
        ->toBe(OrganizationRelationshipType::Vendor);
});

it('rejects an internal role', function (OrganizationRelationshipType|string $role) {
    app(EnsureExternalRole::class)->handle($role);
})->with([
    OrganizationRelationshipType::Member,
    OrganizationRelationshipType::Subsidiary,
    'member',
])->throws(InvalidArgumentException::class);
