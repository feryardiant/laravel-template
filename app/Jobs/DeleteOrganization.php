<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\FileStorage;
use App\Actions\Organizations\ListSubsidiaries;
use App\Enums\OrganizationRelationshipType;
use App\Enums\OwnershipTransferStatus;
use App\Models\Organization;
use App\Models\OrganizationOwnershipTransfer;
use App\Models\OrganizationRelationship;
use App\Models\User;
use App\Notifications\OrganizationDeletedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Pennant\FeatureManager;

final class DeleteOrganization implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Organization $organization,
        public ?int $actorId = null,
    ) {
        // .
    }

    /**
     * Delete the organization and every nested organization under it.
     */
    public function handle(FileStorage $fileStorage): void
    {
        $organization = $this->organization->fresh();

        if (! $organization instanceof Organization || $organization->isRootTenant()) {
            return;
        }

        $root = Organization::query()->rootTenant()->first();

        if ($root === null) {
            return;
        }

        $actor = $this->actorId === null ? null : User::query()->find($this->actorId);

        if (! $this->authorize($actor, $organization, $root)) {
            return;
        }

        $branch = app(ListSubsidiaries::class)
            ->handle($organization)
            ->pluck('organization')
            ->prepend($organization)
            ->all();

        $logoPaths = [];
        $notifications = [];

        DB::transaction(function () use ($branch, &$logoPaths, &$notifications): void {
            foreach (array_reverse($branch) as $subsidiary) {
                $members = $this->activeMembers($subsidiary)
                    ->reject(fn (User $user): bool => $user->getKey() === $this->actorId)
                    ->values();

                if ($members->isNotEmpty()) {
                    $notifications[] = [$subsidiary->name, $members];
                }

                $logoPaths[] = $this->purge($subsidiary);
            }
        });

        foreach ($logoPaths as $logoPath) {
            $fileStorage->delete($logoPath);
        }

        foreach ($notifications as [$name, $members]) {
            Notification::send($members, new OrganizationDeletedNotification($name, $actor?->name));
        }
    }

    /**
     * Determine whether the actor may delete the organization.
     */
    private function authorize(?User $actor, Organization $organization, Organization $root): bool
    {
        if (! $actor instanceof User) {
            return false;
        }

        if ($actor->roleIn($organization)?->isOwner() === true && $organization->activeOwnerCount() === 1) {
            return true;
        }

        if ($actor->roleIn($root)?->isOwner() !== true) {
            return false;
        }

        return app(ListSubsidiaries::class)
            ->handle($root)
            ->contains(fn (array $row): bool => $row['organization']->is($organization));
    }

    /**
     * Remove the organization's owned data.
     *
     * Organizations referenced by other tenants keep a minimal tombstone so
     * those relationships can still be displayed.
     */
    private function purge(Organization $organization): ?string
    {
        $logoPath = $organization->getRawOriginal('logo_url');

        $organization->address()->delete();

        DB::table('features')
            ->where('scope', app(FeatureManager::class)->serializeScope($organization))
            ->delete();

        $externallyReferenced = OrganizationRelationship::query()
            ->where('stakeholder_type', $organization->getMorphClass())
            ->where('stakeholder_id', $organization->getKey())
            ->whereIn('type', OrganizationRelationshipType::externals())
            ->exists();

        if (! $externallyReferenced) {
            $organization->forceDelete();

            return $logoPath;
        }

        OrganizationRelationship::query()
            ->where('organization_id', $organization->getKey())
            ->delete();

        OrganizationRelationship::query()
            ->whereStakeholder($organization)
            ->whereIn('type', OrganizationRelationshipType::internals())
            ->delete();

        OrganizationOwnershipTransfer::query()
            ->where('organization_id', $organization->getKey())
            ->where('status', OwnershipTransferStatus::Pending)
            ->update(['status' => OwnershipTransferStatus::Cancelled]);

        $organization->update([
            'summary' => null,
            'logo_url' => null,
        ]);

        $organization->delete();

        return $logoPath;
    }

    /**
     * Active, credentialed members of the organization.
     *
     * @return Collection<int, User>
     */
    private function activeMembers(Organization $organization): Collection
    {
        $today = now()->toDateString();

        return $organization->members()
            ->whereHas('user')
            ->where(fn (Builder $query): Builder => $query->whereNull('organization_relationships.start_date')
                ->orWhere('organization_relationships.start_date', '<=', $today))
            ->where(fn (Builder $query): Builder => $query->whereNull('organization_relationships.finish_date')
                ->orWhere('organization_relationships.finish_date', '>=', $today))
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter(fn ($user): bool => $user instanceof User)
            ->unique('id')
            ->values();
    }
}
