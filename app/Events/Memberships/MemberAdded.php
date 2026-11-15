<?php

declare(strict_types=1);

namespace App\Events\Memberships;

use App\Enums\AuditEvent;
use App\Events\AuditableEvent;

final class MemberAdded extends AuditableEvent
{
    public function auditEvent(): AuditEvent
    {
        return AuditEvent::MemberAdded;
    }
}
