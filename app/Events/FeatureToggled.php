<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\AuditEvent;

final class FeatureToggled extends AuditableEvent
{
    public function auditEvent(): AuditEvent
    {
        return AuditEvent::FeatureToggled;
    }
}
