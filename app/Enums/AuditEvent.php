<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A recorded change in an organization's audit trail.
 */
enum AuditEvent: string
{
    case MemberAdded = 'member.added';
    case MemberRoleChanged = 'member.role_changed';
    case MemberRemoved = 'member.removed';
    case MemberLeft = 'member.left';

    case ContactCreated = 'contact.created';
    case ContactRenewed = 'contact.renewed';
    case ContactUpdated = 'contact.updated';
    case ContactDeleted = 'contact.deleted';

    case OrganizationCreated = 'organization.created';
    case OrganizationUpdated = 'organization.updated';
    case OrganizationDeleted = 'organization.deleted';

    case FeatureToggled = 'feature.toggled';

    case ApprovalRequested = 'approval.requested';
    case ApprovalAccepted = 'approval.accepted';
    case ApprovalDeclined = 'approval.declined';
    case ApprovalCancelled = 'approval.cancelled';

    public function label(): string
    {
        return match ($this) {
            self::MemberAdded => __('Member added'),
            self::MemberRoleChanged => __('Member role changed'),
            self::MemberRemoved => __('Member removed'),
            self::MemberLeft => __('Member left'),
            self::ContactCreated => __('Contact created'),
            self::ContactRenewed => __('Contact renewed'),
            self::ContactUpdated => __('Contact updated'),
            self::ContactDeleted => __('Contact deleted'),
            self::OrganizationCreated => __('Organization created'),
            self::OrganizationUpdated => __('Organization updated'),
            self::OrganizationDeleted => __('Organization deleted'),
            self::FeatureToggled => __('Feature toggled'),
            self::ApprovalRequested => __('Approval requested'),
            self::ApprovalAccepted => __('Approval accepted'),
            self::ApprovalDeclined => __('Approval declined'),
            self::ApprovalCancelled => __('Approval cancelled'),
        };
    }
}
