<?php

declare(strict_types=1);

namespace App\Events\Contacts;

use App\Enums\AuditEvent;
use App\Events\AuditableEvent;

final class ContactCreated extends AuditableEvent
{
    public function auditEvent(): AuditEvent
    {
        return AuditEvent::ContactCreated;
    }
}
