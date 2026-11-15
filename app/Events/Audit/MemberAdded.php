<?php

declare(strict_types=1);

namespace App\Events\Audit;

use App\Enums\AuditEvent;

final class MemberAdded extends AuditEventOccurred
{
    public function auditEvent(): AuditEvent
    {
        return AuditEvent::MemberAdded;
    }
}
