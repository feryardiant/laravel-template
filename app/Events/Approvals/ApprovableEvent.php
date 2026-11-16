<?php

declare(strict_types=1);

namespace App\Events\Approvals;

use App\Events\AuditableEvent;
use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Queue\SerializesModels;

abstract class ApprovableEvent extends AuditableEvent
{
    use SerializesModels;

    public function __construct(
        public ApprovalRequest $request,
        ?User $actor,
        public ?string $reason = null,
    ) {
        parent::__construct($request->organization, $actor, $request, array_filter([
            'type' => $request->type->value,
            'reason' => $reason,
        ]));
    }

}
