<?php

declare(strict_types=1);

namespace App\Events\Contacts;

use App\Enums\AuditEvent;
use App\Events\AuditableEvent;

final class ContactUpdated extends AuditableEvent
{
    public function auditEvent(): AuditEvent
    {
        return AuditEvent::ContactUpdated;
    }
}
