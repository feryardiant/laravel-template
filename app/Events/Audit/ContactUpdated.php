<?php

declare(strict_types=1);

namespace App\Events\Audit;

use App\Enums\AuditEvent;

final class ContactUpdated extends AuditEventOccurred
{
    public function auditEvent(): AuditEvent
    {
        return AuditEvent::ContactUpdated;
    }
}
