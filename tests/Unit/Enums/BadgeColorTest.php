<?php

declare(strict_types=1);

use App\Enums\ApprovalRequestStatus;
use App\Enums\AuditEvent;
use App\Enums\OrganizationRelationshipType;
use App\Enums\RelationshipStatus;
use App\Enums\Role;
use App\Enums\SubsidiaryKind;

dataset('enums', [
    'approval status' => [ApprovalRequestStatus::class],
    'audit event' => [AuditEvent::class],
    'organization relationship type' => [OrganizationRelationshipType::class],
    'relationship status' => [RelationshipStatus::class],
    'role' => [Role::class],
    'subsidiary kind' => [SubsidiaryKind::class],
]);

test('every case has a flux badge color', function (string $enum): void {
    $colors = [
        'zinc', 'red', 'orange', 'amber', 'yellow', 'lime', 'green', 'emerald',
        'teal', 'cyan', 'sky', 'blue', 'indigo', 'violet', 'purple', 'fuchsia',
        'pink', 'rose',
    ];

    foreach ($enum::cases() as $case) {
        expect($case->badgeColor())->toBeIn($colors);
    }
})->with('enums');
