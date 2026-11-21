<?php

declare(strict_types=1);

use App\Actions\Approvals\Audit\ApprovalPayload;
use App\Actions\Audit\AuditPayload;
use App\Actions\Contacts\Audit\ContactDeletedPayload;
use App\Actions\Contacts\Audit\ContactRelationPayload;
use App\Actions\Contacts\Audit\ContactUpdatedPayload;
use App\Actions\Memberships\Audit\MemberAddedPayload;
use App\Actions\Memberships\Audit\MemberLeftPayload;
use App\Actions\Memberships\Audit\MemberRemovedPayload;
use App\Actions\Memberships\Audit\MemberRoleChangedPayload;
use App\Actions\Organizations\Audit\FeatureToggledPayload;
use App\Actions\Organizations\Audit\OrganizationCreatedPayload;
use App\Actions\Organizations\Audit\OrganizationUpdatedPayload;
use App\Enums\ApprovalRequestType;
use App\Enums\AuditEvent;
use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Enums\SubsidiaryKind;
use App\Models\Person;

test('an audit payload round-trips through its stored array', function (AuditPayload $payload) {
    expect($payload::fromArray($payload->toArray()))->toEqual($payload);
})->with([
    'member added' => fn (): AuditPayload => new MemberAddedPayload(Role::Member),
    'member role changed' => fn (): AuditPayload => new MemberRoleChangedPayload(Role::Member, Role::Admin),
    'member removed' => fn (): AuditPayload => new MemberRemovedPayload('manager', true),
    'member left' => fn (): AuditPayload => new MemberLeftPayload('approval', 12),
    'contact relation' => fn (): AuditPayload => new ContactRelationPayload('individual', OrganizationRelationshipType::Customer, ['code']),
    'contact updated' => fn (): AuditPayload => new ContactUpdatedPayload(['name', 'summary']),
    'contact deleted' => fn (): AuditPayload => new ContactDeletedPayload(true),
    'organization created' => fn (): AuditPayload => new OrganizationCreatedPayload(4, SubsidiaryKind::Branch),
    'organization updated' => fn (): AuditPayload => new OrganizationUpdatedPayload(['name'], true),
    'feature toggled' => fn (): AuditPayload => new FeatureToggledPayload('contact-gender', false),
    'approval' => fn (): AuditPayload => new ApprovalPayload(ApprovalRequestType::MembershipLeave, 'member_removed'),
]);

test('an audit payload summarizes itself from its subject', function () {
    $person = (new Person)->forceFill(['name' => 'Fery']);

    $payload = MemberRoleChangedPayload::fromEvent(AuditEvent::MemberRoleChanged, $person, [
        'from' => Role::Member->value,
        'to' => Role::Admin->value,
    ]);

    expect((string) $payload)->toBe(__('Role of :member changed', ['member' => 'Fery']))
        ->and($payload->details())->toBe([Role::Member->label().' → '.Role::Admin->label()]);
});

test('an audit payload renders its own details', function () {
    $contact = new ContactUpdatedPayload(['start_date', 'logo_url']);
    $feature = new FeatureToggledPayload('contact-gender', true);
    $approval = new ApprovalPayload(ApprovalRequestType::MembershipLeave, 'member_removed');

    expect($contact->details())->toBe([__('Fields').': '.__('Start date').', '.__('Organization Logo')])
        ->and($feature->details())->toBe([__(':feature: :state', [
            'feature' => __('Gender field in contact'),
            'state' => __('On'),
        ])])
        ->and($approval->details())->toBe([
            ApprovalRequestType::MembershipLeave->label(),
            __('Reason').': '.__('Member removed'),
        ]);
});
