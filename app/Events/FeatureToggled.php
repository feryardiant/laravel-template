<?php

declare(strict_types=1);

namespace App\Events;

use App\Actions\Organizations\Audit\FeatureToggledPayload;
use App\Enums\AuditEvent;
use App\Models\Organization;
use App\Models\User;

final class FeatureToggled extends AuditableEvent
{
    public function __construct(
        Organization $organization,
        ?User $actor,
        string $feature,
        bool $enabled,
    ) {
        parent::__construct(
            $organization,
            $actor,
            $organization,
            new FeatureToggledPayload($feature, $enabled),
        );
    }

    public function auditEvent(): AuditEvent
    {
        return AuditEvent::FeatureToggled;
    }
}
