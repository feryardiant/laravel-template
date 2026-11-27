<?php

declare(strict_types=1);

use App\Actions\Tenancies\ProvisionTenant;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use Tests\Stress\TestCase;

describe('Setup', function (): void {
    beforeEach(function (): void {
        /** @var TestCase $this */
        $this->requireWriteQuery();
    });

    it('provisions the root tenant', function (): void {
        User::factory()->withoutProfile()->create([
            'name' => 'Placeholder',
            'email' => config('setup.initial_email'),
            'handle' => 'setup',
        ]);

        /** @var TestCase $this */
        $this->measure('setup: provision root tenant', fn () => app(ProvisionTenant::class)
            ->handle('First Owner', 'first-owner@example.test', 'password'));

        $root = Organization::query()->rootTenant()->firstOrFail();
        $owner = Person::query()->where('user_id', User::query()->where('email', 'first-owner@example.test')->value('id'))->firstOrFail();

        expect($root->isRootTenant())->toBeTrue()
            ->and($root->members()->whereKey($owner->getKey())->exists())->toBeTrue();
    })->group('query-setup');
});
