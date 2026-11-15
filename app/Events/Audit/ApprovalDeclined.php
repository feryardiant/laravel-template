<?php

declare(strict_types=1);

namespace App\Events\Audit;

use App\Enums\AuditEvent;

final class ApprovalDeclined extends AuditEventOccurred
{
    public function auditEvent(): AuditEvent
    {
        return AuditEvent::ApprovalDeclined;
    }
}
