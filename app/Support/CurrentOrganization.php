<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Holds the active workspace organization for the current request.
 */
final class CurrentOrganization
{
    public const SESSION_KEY = 'current_organization_id';

    private const ORGANIZATION_KEY = 'current_organization';

    private const ORGANIZATIONS_KEY = 'current_organizations';

    /**
     * Resolve the organization the user is currently working in.
     */
    public function get(): ?Organization
    {
        $request = request();

        if ($request->attributes->has(self::ORGANIZATION_KEY)) {
            $organization = $request->attributes->get(self::ORGANIZATION_KEY);

            return $organization instanceof Organization ? $organization : null;
        }

        return $this->remember()['organization'];
    }

    /**
     * Get the organizations the user is currently a member of.
     *
     * @return Collection<int, Organization>
     */
    public function organizations(): Collection
    {
        $request = request();
        $organizations = $request->attributes->get(self::ORGANIZATIONS_KEY);

        if ($organizations instanceof Collection) {
            return $organizations;
        }

        return $this->remember()['organizations'];
    }

    /**
     * Store the given organization as the user's current one.
     */
    public function switch(Organization|int $organization): bool
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        $organization = $organization instanceof Organization
            ? $organization->getKey()
            : $organization;

        if (! $user->profile->organizations()->whereKey($organization)->exists()) {
            return false;
        }

        session([self::SESSION_KEY => $organization]);

        $this->forget();

        return true;
    }

    /**
     * Drop the request snapshot so the next read resolves again.
     */
    public function forget(): void
    {
        request()->attributes->remove(self::ORGANIZATION_KEY);
        request()->attributes->remove(self::ORGANIZATIONS_KEY);
    }

    /**
     * Resolve the current organization and memberships once per request.
     *
     * @return array{organization: ?Organization, organizations: Collection<int, Organization>}
     */
    private function remember(): array
    {
        $state = $this->resolve();

        request()->attributes->set(self::ORGANIZATION_KEY, $state['organization']);
        request()->attributes->set(self::ORGANIZATIONS_KEY, $state['organizations']);

        return $state;
    }

    /**
     * Resolve the current organization from the session or the person's default.
     *
     * @return array{organization: ?Organization, organizations: Collection<int, Organization>}
     */
    private function resolve(): array
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return ['organization' => null, 'organizations' => new Collection];
        }

        $organizations = $user->profile->organizations()->get();
        $organization = null;

        if ($organizationId = session(self::SESSION_KEY)) {
            $organization = $organizations->first(
                fn (Organization $candidate): bool => $candidate->getKey() === (int) $organizationId
            );
        }

        $organization ??= $organizations->first(
            fn (Organization $candidate): bool => $candidate->stakeholder->is_primary === true
        ) ?? $organizations->first();

        return ['organization' => $organization, 'organizations' => $organizations];
    }
}
