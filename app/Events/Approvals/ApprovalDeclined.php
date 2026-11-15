<?php

declare(strict_types=1);

namespace App\Events\Approvals;

use App\Enums\AuditEvent;

final class ApprovalDeclined extends ApprovableEvent
{
    public function auditEvent(): AuditEvent
    {
        return AuditEvent::ApprovalDeclined;
    }
}
