<?php

declare(strict_types=1);

use App\Actions\FileStorage;
use App\Enums\Feature;
use App\Jobs\DeleteOrganization;
use App\Livewire\OrgSettings\ApprovalHistory;
use App\Livewire\OrgSettings\Audit as AuditPage;
use App\Livewire\OrgSettings\Features;
use App\Livewire\OrgSettings\General;
use App\Livewire\OrgSettings\Subsidiaries;
use App\Livewire\Settings\Organizations\Create as CreateOrganizationForm;
use App\Models\Organization;
use App\Repositories\ApprovalRepository;
use App\Repositories\OrganizationRepository;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Stress\TestCase;

describe('Livewire pages', function (): void {
    beforeEach(function (): void {
        /** @var TestCase $this */
        $this->requireQuery();
        $this->switchTo($this->benchOwner(), $this->bench());
    });

    it('renders the organization settings', function (): void {
        /** @var TestCase $this */
        $this->measure('bench org general', fn () => Livewire::test(General::class), runs: 3);
    })->group('query-read');

    it('renders the feature settings', function (): void {
        /** @var TestCase $this */
        $this->measure('bench org features', fn () => Livewire::test(Features::class), runs: 3);
    })->group('query-read');

    it('renders the audit log', function (): void {
        /** @var TestCase $this */
        $this->measure('bench audit', fn () => Livewire::test(AuditPage::class), runs: 3);
    })->group('query-read');

    it('renders the approval history', function (): void {
        /** @var TestCase $this */
        $this->measure('bench approval history', fn () => Livewire::test(ApprovalHistory::class), runs: 3);
    })->group('query-read');

    it('renders the root audit log', function (): void {
        /** @var TestCase $this */
        $this->switchTo($this->rootOwner(), $this->root());

        $this->measure('root audit', fn () => Livewire::test(AuditPage::class), runs: 3);
    })->group('query-read');

    it('renders the root approval history', function (): void {
        /** @var TestCase $this */
        $this->switchTo($this->rootOwner(), $this->root());

        $this->measure('root approval history', fn () => Livewire::test(ApprovalHistory::class), runs: 3);
    })->group('query-read');

    it('renders the subsidiary tree', function (): void {
        /** @var TestCase $this */
        $this->switchTo($this->rootOwner(), $this->root());

        $this->measure('root subsidiaries', fn () => Livewire::test(Subsidiaries::class), runs: 3);
    })->group('query-read');

    it('renders the root general settings', function (): void {
        /** @var TestCase $this */
        $this->switchTo($this->rootOwner(), $this->root());

        $this->measure('root org general', fn () => Livewire::test(General::class), runs: 3);
    })->group('query-read');
});

describe('Query plans', function (): void {
    beforeEach(function (): void {
        /** @var TestCase $this */
        $this->requireQuery();
    });

    it('explains the approval history', function (): void {
        /** @var TestCase $this */
        $sql = $this->capture(fn () => app(ApprovalRepository::class)->getForOrganization($this->bench()));

        $this->explain('approval history', $sql[0] ?? '');
    })->group('query-read');

    it('explains the subsidiary tree', function (): void {
        /** @var TestCase $this */
        $sql = $this->capture(fn () => app(OrganizationRepository::class)->getSubsidiaries($this->root()));

        $this->explain('subsidiary edges', $sql[0] ?? '');
        $this->explain('subsidiary organizations', $sql[1] ?? '');
    })->group('query-read');
});

describe('Actions', function (): void {
    beforeEach(function (): void {
        /** @var TestCase $this */
        $this->requireQuery();
        $this->switchTo($this->benchOwner(), $this->bench());
    });

    it('updates the organization', function (): void {
        /** @var TestCase $this */
        $name = 'Benchmark Tenant '.$this->suffix();

        $this->measure('organization update', fn () => Livewire::test(General::class)
            ->set('name', $name)
            ->set('address', [
                'line' => '', 'rt' => '', 'rw' => '', 'postal_code' => '',
                'province_code' => '', 'regency_code' => '', 'district_code' => '', 'village_code' => '',
            ])
            ->call('update'));

        expect(Organization::query()->findOrFail(2)->name)->toBe($name);
    })->group('query-write');

    it('toggles a feature', function (): void {
        /** @var TestCase $this */
        $this->measure('feature toggle (contact gender)', fn () => Livewire::test(Features::class)
            ->set('features.contact-gender', true));

        expect(Feature::ContactGender->enabled($this->bench()))->toBeTrue();
    })->group('query-write');

    it('creates an owned organization', function (): void {
        /** @var TestCase $this */
        $name = 'Baseline Own Org '.$this->suffix();

        $this->measure('organization create (own)', fn () => Livewire::test(CreateOrganizationForm::class)
            ->call('open')
            ->set('name', $name)
            ->set('parentId', (string) $this->bench()->getKey())
            ->set('kind', 'subsidiary')
            ->call('create'));

        expect(Organization::query()->where('name', $name)->exists())->toBeTrue();
    })->group('query-write');

    it('creates a subsidiary', function (): void {
        /** @var TestCase $this */
        $this->switchTo($this->rootOwner(), $this->root());
        $name = 'Baseline Subsidiary '.$this->suffix();

        $this->measure('subsidiary create', fn () => Livewire::test(CreateOrganizationForm::class, ['internal' => true])
            ->call('open')
            ->set('name', $name)
            ->set('kind', 'subsidiary')
            ->call('create'));

        expect(Organization::query()->where('name', $name)->exists())->toBeTrue();
    })->group('query-write');
});

describe('Jobs', function (): void {
    beforeEach(function (): void {
        /** @var TestCase $this */
        $this->requireQuery();
    });

    it('runs the core deletion', function (): void {
        Notification::fake();
        $organization = Organization::query()->findOrFail(10);

        /** @var TestCase $this */
        $this->measure('delete job core (notifications faked)', fn () => (new DeleteOrganization($organization, $this->rootOwner()->getKey()))
            ->handle(app(FileStorage::class)));

        $deleted = Organization::withTrashed()->find(10);
        expect($deleted === null || $deleted->trashed())->toBeTrue();
    })->group('query-delete-core');

    it('runs the full deletion', function (): void {
        $organization = Organization::query()->findOrFail(10);

        /** @var TestCase $this */
        $this->measure('delete organization job (tenant 10)', fn () => (new DeleteOrganization($organization, $this->rootOwner()->getKey()))
            ->handle(app(FileStorage::class)));

        $deleted = Organization::withTrashed()->find(10);
        expect($deleted === null || $deleted->trashed())->toBeTrue();
    })->group('query-delete');
});
