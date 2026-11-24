<?php

declare(strict_types=1);

use App\Enums\OrganizationRelationshipType;
use App\Models\Organization;
use App\Repositories\OrganizationRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('previews a subsidiary and its nested descendants', function () {
    $root = Organization::factory()->asRootTenant()->create();
    $alpha = Organization::factory()->asTenantOf($root)->create(['name' => 'Alpha']);
    $beta = Organization::factory()->asTenantOf($root)->create(['name' => 'Beta']);

    $root->subsidiaries()->attach($alpha, [
        'type' => OrganizationRelationshipType::Subsidiary,
        'start_date' => now(),
    ]);
    $alpha->subsidiaries()->attach($beta, [
        'type' => OrganizationRelationshipType::Subsidiary,
        'start_date' => now(),
    ]);

    $preview = app(OrganizationRepository::class)->preview($root, $alpha->getKey());

    expect($preview)->not->toBeNull()
        ->and($preview['organization']->is($alpha))->toBeTrue()
        ->and($preview['count'])->toBe(2);
});

it('returns null outside the root tenant', function () {
    $tenant = Organization::factory()->create();

    expect(app(OrganizationRepository::class)->preview($tenant, 1))->toBeNull();
});
