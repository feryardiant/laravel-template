<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Contacts\SearchContact;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use App\Support\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Serves contact candidates for the name autocomplete.
 */
class ContactLookupController extends Controller
{
    /**
     * Return contact candidates for the Name autocomplete.
     */
    public function __invoke(Request $request, SearchContact $search, CurrentOrganization $currentOrganization): JsonResponse
    {
        $organization = $currentOrganization->get();

        if (! $organization instanceof Organization) {
            return response()->json([]);
        }

        Gate::authorize('createContact', $organization);

        $candidates = $search->handle($organization, trim((string) $request->query('q', '')));

        $expired = OrganizationRelationship::query()
            ->forOrganization($organization)
            ->externals()
            ->expired()
            ->whereIn('stakeholder_id', $candidates->pluck('id')->all())
            ->get(['stakeholder_type', 'stakeholder_id'])
            ->keyBy(fn (OrganizationRelationship $relationship): string => $relationship->stakeholder_type.'#'.$relationship->stakeholder_id);

        $results = $candidates
            ->map(fn (Organization|Person $entity): array => [
                'label' => $this->candidateLabel($entity, $expired->has($entity->getMorphClass().'#'.$entity->getKey())),
                'value' => ($entity instanceof Organization ? 'organization' : 'individual').':'.$entity->uuid,
            ])
            ->values();

        return response()->json($results);
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
