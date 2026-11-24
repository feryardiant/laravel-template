<?php

declare(strict_types=1);

namespace App\Events\Approvals;

use App\Enums\AuditEvent;

/**
 * Raised when an approval request is submitted.
 */
final class ApprovalRequested extends ApprovableEvent
{
    /**
     * The audit event recorded for this change.
     */
    public function auditEvent(): AuditEvent
    {
        return AuditEvent::ApprovalRequested;
    }
}
