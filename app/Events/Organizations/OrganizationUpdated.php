<?php

declare(strict_types=1);

namespace App\Events\Organizations;

use App\Enums\AuditEvent;
use App\Events\AuditableEvent;

final class OrganizationUpdated extends AuditableEvent
{
    public function auditEvent(): AuditEvent
    {
        return AuditEvent::OrganizationUpdated;
    }
}
