<?php

declare(strict_types=1);

namespace App\Actions\Memberships;

use App\Actions\Approvals\MembershipLeave;
use App\Models\Organization;
use App\Models\User;
use InvalidArgumentException;

/**
 * Leaves a workspace directly or requests an owner's approval.
 */
final class LeaveWorkspace
{
    public function __construct(
        private LeaveOrganization $leaveOrganization,
        private MembershipLeave $leaveMembership,
    ) {
        // .
    }

    /**
     * Leave directly, request approval as an admin, or report why not.
     *
     * @return 'requested'|'left'|'pending'|'last_owner'
     */
    public function handle(Organization $organization, User $user): string
    {
        if ($user->roleIn($organization)?->isAdmin() === true) {
            try {
                $this->leaveMembership->request($organization, $user);

                return 'requested';
            } catch (InvalidArgumentException) {
                return 'pending';
            }
        }

        return $this->leaveOrganization->handle($user, $organization)
            ? 'left'
            : 'last_owner';
    }
}
