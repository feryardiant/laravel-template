<?php

declare(strict_types=1);

namespace App\Actions\Memberships;

use App\Actions\Audit\RecordAudit;
use App\Actions\Relationships\RelationshipTimeline;
use App\Enums\ApprovalRequestStatus;
use App\Enums\AuditEvent;
use App\Enums\OrganizationRelationshipType;
use App\Enums\RelationshipPeriodReason;
use App\Events\MemberRemoved;
use App\Models\ApprovalRequest;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

final class LeaveOrganization
{
    public function __construct(
        private RelationshipTimeline $timeline,
        private RecordAudit $audit,
    ) {
        // .
    }

    /**
     * Remove the user's own membership from the organization.
     */
    public function handle(User $user, Organization $organization): bool
    {
        $person = $user->person;

        if ($person === null) {
            return false;
        }

        $left = DB::transaction(function () use ($person, $organization, $user): bool {
            $organization = Organization::query()->whereKey($organization->getKey())->lockForUpdate()->first();

            if ($organization === null) {
                return false;
            }

            $role = $person->roleIn($organization);

            // Admins leave through `RequestMembershipLeave`, which needs an
            // owner's approval; only owners and members detach immediately.
            if ($role === null || $role->isAdmin() || ($role->isOwner() && $organization->activeOwnerCount() === 1)) {
                return false;
            }

            $relationship = OrganizationRelationship::query()
                ->forOrganization($organization)
                ->whereStakeholder($person)
                ->where('type', OrganizationRelationshipType::Member)
                ->first();

            $period = $relationship !== null
                ? $this->timeline->close($relationship, RelationshipPeriodReason::Left, $user)
                : null;

            $organization->members()->detach($person->getKey());

            $this->audit->handle($organization, $user, AuditEvent::MemberLeft, $person, [
                'via' => 'self',
            ], $period);

            $pending = ApprovalRequest::query()
                ->pending()
                ->where('organization_id', $organization->getKey())
                ->where('requester_person_id', $person->getKey())
                ->get();

            foreach ($pending as $request) {
                $request->update([
                    'status' => ApprovalRequestStatus::Cancelled,
                    'cancelled_at' => now(),
                ]);

                $this->audit->handle($organization, $user, AuditEvent::ApprovalCancelled, $request, [
                    'type' => $request->type->value,
                    'reason' => 'member_left',
                ]);
            }

            return true;
        });

        if (! $left) {
            return false;
        }

        try {
            event(new MemberRemoved($organization, $person, null, ! $person->organizations()->exists()));
        } catch (Throwable $exception) {
            report($exception);
        }

        return true;
    }
}
