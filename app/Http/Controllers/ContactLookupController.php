<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Contacts\SearchContactCandidates;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ContactLookupController extends Controller
{
    /**
     * Return contact candidates for the Name autocomplete.
     */
    public function __invoke(Request $request, SearchContactCandidates $search): JsonResponse
    {
        Gate::authorize('createContact', Organization::class);

        $user = $request->user();
        $organization = $user instanceof User ? app(CurrentOrganization::class)->get($user) : null;

        if (! $organization instanceof Organization) {
            return response()->json([]);
        }

        $results = $search->handle($organization, trim((string) $request->query('q', '')))
            ->map(fn (Organization|Person $entity): array => [
                'label' => $entity->name,
                'value' => ($entity instanceof Organization ? 'organization' : 'individual').':'.$entity->uuid,
            ])
            ->values();

        return response()->json($results);
    }
}
