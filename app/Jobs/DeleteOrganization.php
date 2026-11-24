<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\FileStorage;
use App\Enums\ApprovalRequestStatus;
use App\Models\ApprovalRequest;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\User;
use App\Notifications\OrganizationDeletedNotification;
use App\Repositories\OrganizationRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Laravel\Pennant\FeatureManager;

/**
 * Deletes an organization and its nested subsidiaries with their owned data.
 */
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

        $actor = $this->actorId === null ? null : User::query()->find($this->actorId);

        if (! $actor instanceof User || ! Gate::forUser($actor)->allows('delete', $organization)) {
            return;
        }

        $branch = app(OrganizationRepository::class)
            ->getSubsidiaries($organization)
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
            Notification::send($members, new OrganizationDeletedNotification($name, $actor->name));
        }
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

        AuditLog::query()
            ->where('organization_id', $organization->getKey())
            ->delete();

        $organization->address()->delete();

        ApprovalRequest::query()
            ->onlyPendings()
            ->forOrganization($organization)
            ->update([
                'status' => ApprovalRequestStatus::Canceled,
                'canceled_at' => now(),
            ]);

        DB::table('features')
            ->where('scope', app(FeatureManager::class)->serializeScope($organization))
            ->delete();

        $externallyReferenced = OrganizationRelationship::query()
            ->forStakeholder($organization)
            ->externals()
            ->exists();

        if (! $externallyReferenced) {
            $organization->forceDelete();

            return $logoPath;
        }

        OrganizationRelationship::query()
            ->forOrganization($organization)
            ->delete();

        OrganizationRelationship::query()
            ->forStakeholder($organization)
            ->internals()
            ->delete();

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
