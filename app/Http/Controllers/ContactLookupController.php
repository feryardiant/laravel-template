<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Contacts\SearchContact;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ContactLookupController extends Controller
{
    /**
     * Return contact candidates for the Name autocomplete.
     */
    public function __invoke(Request $request, SearchContact $search): JsonResponse
    {
        Gate::authorize('createContact', Organization::class);

        $user = $request->user();
        $organization = $user instanceof User ? app(CurrentOrganization::class)->get($user) : null;

        if (! $organization instanceof Organization) {
            return response()->json([]);
        }

        $results = $search->handle($organization, trim((string) $request->query('q', '')))
            ->map(fn (Organization|Person $entity): array => [
                'label' => $this->candidateLabel($entity, $this->isExpiredFor($organization, $entity)),
                'value' => ($entity instanceof Organization ? 'organization' : 'individual').':'.$entity->uuid,
            ])
            ->values();

        return response()->json($results);
    }

    /**
     * Determine whether the candidate is one of the tenant's expired contacts.
     */
    private function isExpiredFor(Organization $organization, Organization|Person $entity): bool
    {
        return OrganizationRelationship::query()
            ->external()
            ->forOrganization($organization)
            ->expired()
            ->whereStakeholder($entity)
            ->exists();
    }

    /**
     * Disambiguate candidates that share a name.
     */
    private function candidateLabel(Organization|Person $entity, bool $expired = false): string
    {
        $type = $entity instanceof Organization ? __('Organization') : __('Individual');
        $hint = filled($entity->summary)
            ? Str::limit($entity->summary, 40)
            : '#'.Str::substr($entity->uuid, 0, 8);

        return $type.' · '.$entity->name.' — '.$hint.($expired ? ' ('.__('Expired').')' : '');
    }
}
