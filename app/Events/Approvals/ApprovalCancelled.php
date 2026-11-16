<?php

declare(strict_types=1);

namespace App\Events\Approvals;

use App\Enums\AuditEvent;

final class ApprovalCancelled extends ApprovableEvent
{
    public function auditEvent(): AuditEvent
    {
        return AuditEvent::ApprovalCancelled;
    }
}
