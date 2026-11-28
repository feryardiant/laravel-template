<?php

declare(strict_types=1);

use App\Livewire\Settings\Organizations as SettingsOrganizations;
use App\Livewire\Settings\Preferences;
use App\Livewire\Settings\Profile;
use App\Livewire\Settings\Security;
use App\Models\Person;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Stress\TestCase;

describe('Livewire pages', function (): void {
    beforeEach(function (): void {
        /** @var TestCase $this */
        $this->requireQuery();
        $this->switchTo($this->benchOwner(), $this->bench());
    });

    it('renders the organization memberships', function (): void {
        /** @var TestCase $this */
        $this->measure('bench settings organizations', fn () => Livewire::test(SettingsOrganizations::class), runs: 3);
    })->group('query-read');

    it('renders the profile', function (): void {
        /** @var TestCase $this */
        $this->measure('bench settings profile', fn () => Livewire::test(Profile::class), runs: 3);
    })->group('query-read');

    it('renders the preferences', function (): void {
        /** @var TestCase $this */
        $this->measure('bench settings preferences', fn () => Livewire::test(Preferences::class), runs: 3);
    })->group('query-read');

    it('renders the security settings', function (): void {
        /** @var TestCase $this */
        $this->measure('bench settings security', fn () => Livewire::test(Security::class), runs: 3);
    })->group('query-read');
});

describe('Actions', function (): void {
    beforeEach(function (): void {
        /** @var TestCase $this */
        $this->requireWriteQuery();
        $this->switchTo($this->benchOwner(), $this->bench());
    });

    it('updates the profile', function (): void {
        /** @var TestCase $this */
        $summary = 'Bench summary '.$this->suffix();

        $this->measure('profile update', fn () => Livewire::test(Profile::class)
            ->set('summary', $summary)
            ->call('updateProfileInformation'));

        expect(Person::query()->where('user_id', $this->benchOwner()->getKey())->value('summary'))->toBe($summary);
    })->group('query-write');

    it('updates the preferences', function (): void {
        /** @var TestCase $this */
        $response = null;

        $this->measure('preferences update', function () use (&$response): void {
            $response = $this->patch(route('preferences.update'), [
                'locale' => 'en',
                'timezone' => 'UTC',
            ]);
        });

        expect($response?->getStatusCode())->toBe(200)
            ->and($response?->json('timezone'))->toBe('UTC')
            ->and($this->benchOwner()->fresh()->timezone)->toBe('UTC');
    })->group('query-write');

    it('updates the password', function (): void {
        /** @var TestCase $this */
        $password = 'new-password-'.$this->suffix();

        $this->measure('password update', fn () => Livewire::test(Security::class)
            ->set('currentPassword', 'password')
            ->set('password', $password)
            ->set('passwordConfirmation', $password)
            ->call('updatePassword'));

        expect(Hash::check($password, $this->benchOwner()->fresh()->password))->toBeTrue();
    })->group('query-write');
});
