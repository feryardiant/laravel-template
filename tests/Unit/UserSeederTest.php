<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

test('the database seeder creates users with handles', function () {
    /** @var TestCase $this */
    $this->seed();

    expect(User::query()->orderBy('id')->pluck('handle')->all());
    expect(User::query()->whereNull('handle')->exists())->toBeFalse();

    $organization = Organization::query()->rootTenant()->firstOrFail();

    expect($organization->members()->count())->toBe(5);
});
