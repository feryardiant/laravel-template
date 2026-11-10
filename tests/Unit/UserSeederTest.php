<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;
use Tests\TestCase;

test('the database seeder creates users with handles', function () {
    /** @var TestCase $this */
    $this->seed();

    expect(User::query()->orderBy('id')->pluck('handle')->all())
        // ->toBe(['owner', 'admin', 'member1', 'member2', 'member3'])
        ->and(User::query()->whereNull('handle')->exists())->toBeFalse();

    $organization = Organization::query()->rootTenant()->firstOrFail();

    expect($organization->members()->count())->toBe(5);
});
