<?php

declare(strict_types=1);

use App\Enums\OrganizationRelationshipType;
use App\Livewire\Contacts\Form as ContactForm;
use App\Livewire\Contacts\Index as ContactIndex;
use App\Livewire\Contacts\Partials\DeleteContactModal;
use App\Livewire\Contacts\Partials\DeleteContactsModal;
use App\Livewire\Contacts\View as ContactView;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use App\Repositories\ContactRepository;
use Livewire\Livewire;
use Tests\Stress\TestCase;

describe('Livewire pages', function (): void {
    beforeEach(function (): void {
        /** @var TestCase $this */
        $this->requireQuery();
        $this->switchTo($this->benchOwner(), $this->bench());
    });

    it('renders the contacts index', function (): void {
        /** @var TestCase $this */
        $this->measure('bench contacts index', fn () => Livewire::test(ContactIndex::class), runs: 3);
    })->group('query-read');

    it('renders the second contacts page', function (): void {
        /** @var TestCase $this */
        $this->measure('bench contacts page 2', fn () => Livewire::test(ContactIndex::class)->call('setPage', 2), runs: 3);
    })->group('query-read');

    it('renders the searched contacts page', function (): void {
        /** @var TestCase $this */
        $this->measure('bench contacts search', fn () => Livewire::test(ContactIndex::class)->set('search', 'Org 2-'), runs: 3);
    })->group('query-read');

    it('renders a contact', function (): void {
        /** @var TestCase $this */
        $contact = $this->benchContact('Org 2-40');

        $this->measure('bench contact view', fn () => Livewire::test(ContactView::class, [
            'stakeholder' => $contact->stakeholder->uuid,
        ]), runs: 3);
    })->group('query-read');

    it('opens the contact edit form', function (): void {
        /** @var TestCase $this */
        $contact = $this->benchContact('Org 2-41');

        $this->measure('bench contact edit', fn () => Livewire::test(ContactForm::class)->call('openEdit', $contact->getKey()), runs: 3);
    })->group('query-read');

    it('measures the contact lookup', function (): void {
        /** @var TestCase $this */
        $this->measure('bench contact lookup', fn () => $this->getJson(route('contacts.lookup', ['q' => 'Shared']))->assertOk(), runs: 3);
    })->group('query-read');
});

describe('Query plans', function (): void {
    beforeEach(function (): void {
        /** @var TestCase $this */
        $this->requireQuery();
    });

    it('explains the contact list and search', function (): void {
        /** @var TestCase $this */
        $bench = $this->bench();
        $sql = $this->capture(fn () => app(ContactRepository::class)->get($bench, ''));
        $this->explain('contacts count', $sql[0] ?? '');
        $this->explain('contacts page', $sql[1] ?? '');

        $sql = $this->capture(fn () => app(ContactRepository::class)->get($bench, 'Org 2-'));
        $this->explain('contacts search count', $sql[0] ?? '');
    })->group('query-read');
});

describe('Bulk actions', function (): void {
    beforeEach(function (): void {
        /** @var TestCase $this */
        $this->requireWriteQuery();
        $this->switchTo($this->benchOwner(), $this->bench());
    });

    it('selects all contacts on the page', function (): void {
        /** @var TestCase $this */
        $this->measure('contacts toggleSelectAll (10 rows)', fn () => Livewire::test(ContactIndex::class)->call('toggleSelectAll'));
    })->group('query-bulk');

    it('bulk deletes contacts', function (): void {
        /** @var TestCase $this */
        $ids = OrganizationRelationship::query()
            ->where('organization_id', 2)
            ->where('stakeholder_type', Organization::class)
            ->whereIn('type', ['customer', 'supplier', 'vendor'])
            ->orderBy('id')
            ->limit(10)
            ->pluck('id')
            ->all();

        $this->measure('contacts bulk delete (10 rows)', fn () => Livewire::test(DeleteContactsModal::class)
            ->call('confirm', $ids)
            ->call('delete'));

        expect(OrganizationRelationship::query()->whereKey($ids)->exists())->toBeFalse();
    })->group('query-bulk');
});

describe('Actions', function (): void {
    beforeEach(function (): void {
        /** @var TestCase $this */
        $this->requireWriteQuery();
        $this->switchTo($this->benchOwner(), $this->bench());
    });

    it('creates an individual contact', function (): void {
        /** @var TestCase $this */
        $name = 'Baseline Individual '.$this->suffix();

        $this->measure('contact create (individual)', fn () => Livewire::test(ContactForm::class)
            ->call('openCreate')
            ->set('type', 'individual')
            ->set('name', $name)
            ->set('role', OrganizationRelationshipType::Customer->value)
            ->set('startDate', now()->toDateString())
            ->set('contactEmail', 'individual-'.$this->suffix().'@example.test')
            ->set('contactPhone', '081234567890')
            ->call('save'));

        expect(Person::query()->where('name', $name)->exists())->toBeTrue();
    })->group('query-write');

    it('creates an organization contact', function (): void {
        /** @var TestCase $this */
        $name = 'Baseline Organization '.$this->suffix();

        $this->measure('contact create (organization)', fn () => Livewire::test(ContactForm::class)
            ->call('openCreate')
            ->set('type', 'organization')
            ->set('name', $name)
            ->set('contactName', 'Baseline Contact '.$this->suffix())
            ->set('role', OrganizationRelationshipType::Customer->value)
            ->set('startDate', now()->toDateString())
            ->set('contactEmail', 'org-'.$this->suffix().'@example.test')
            ->set('contactPhone', '081234567891')
            ->set('contactPersonEmail', 'contact-'.$this->suffix().'@example.test')
            ->set('contactPersonPhone', '081234567892')
            ->call('save'));

        expect(Organization::query()->where('name', $name)->exists())->toBeTrue();
    })->group('query-write');

    it('updates a contact', function (): void {
        /** @var TestCase $this */
        $contact = $this->benchContact('Org 2-1');
        $name = 'Org 2-1 Updated '.$this->suffix();

        $this->measure('contact update', fn () => Livewire::test(ContactForm::class)
            ->call('openEdit', $contact->getKey())
            ->set('name', $name)
            ->call('save'));

        expect(Organization::query()->where('name', $name)->exists())->toBeTrue();
    })->group('query-write');

    it('deletes a contact', function (): void {
        /** @var TestCase $this */
        $contact = $this->benchContact('Org 2-2');

        $this->measure('contact delete', fn () => Livewire::test(DeleteContactModal::class)
            ->call('confirm', $contact->getKey())
            ->call('delete'));

        expect(OrganizationRelationship::query()->whereKey($contact->getKey())->exists())->toBeFalse();
    })->group('query-write');
});
