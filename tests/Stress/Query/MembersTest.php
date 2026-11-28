<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Livewire\Members\Form as MemberForm;
use App\Livewire\Members\Index as MemberIndex;
use App\Livewire\Members\Partials\RemoveMemberModal;
use App\Livewire\Members\Partials\RemoveMembersModal;
use App\Livewire\Members\View as MemberView;
use App\Models\Person;
use App\Repositories\MembershipRepository;
use Livewire\Livewire;
use Tests\Stress\TestCase;

describe('Livewire pages', function (): void {
    beforeEach(function (): void {
        /** @var TestCase $this */
        $this->requireQuery();
        $this->switchTo($this->benchOwner(), $this->bench());
    });

    it('renders the members index', function (): void {
        /** @var TestCase $this */
        $this->measure('bench members index', fn () => Livewire::test(MemberIndex::class), runs: 3);
    })->group('query-read');

    it('renders the searched members page', function (): void {
        /** @var TestCase $this */
        $this->measure('bench members search', fn () => Livewire::test(MemberIndex::class)->set('search', 'Member 2-'), runs: 3);
    })->group('query-read');

    it('renders a member', function (): void {
        /** @var TestCase $this */
        $member = $this->benchMember('Member 2-3');

        $this->measure('bench member view', fn () => Livewire::test(MemberView::class, ['person' => $member]), runs: 3);
    })->group('query-read');

    it('opens the member edit form', function (): void {
        /** @var TestCase $this */
        $member = $this->benchMember('Member 2-4');

        $this->measure('bench member edit', fn () => Livewire::test(MemberForm::class)
            ->call('editMember', $member->getKey())
            ->assertSet('showModal', true), runs: 3);
    })->group('query-read');
});

describe('Query plans', function (): void {
    beforeEach(function (): void {
        /** @var TestCase $this */
        $this->requireQuery();
    });

    it('explains the member list', function (): void {
        /** @var TestCase $this */
        $bench = $this->bench();
        $sql = $this->capture(fn () => app(MembershipRepository::class)->get($bench, ''));

        $this->explain('members count', $sql[0] ?? '');
        $this->explain('members page', $sql[1] ?? '');
    })->group('query-read');
});

describe('Bulk actions', function (): void {
    beforeEach(function (): void {
        /** @var TestCase $this */
        $this->requireWriteQuery();
        $this->switchTo($this->benchOwner(), $this->bench());
    });

    it('selects all members on the page', function (): void {
        /** @var TestCase $this */
        $this->measure('members toggleSelectAll (10 rows)', fn () => Livewire::test(MemberIndex::class)->call('toggleSelectAll'));
    })->group('query-bulk');

    it('bulk removes members', function (): void {
        /** @var TestCase $this */
        $ids = Person::query()
            ->whereIn('id', $this->bench()->members()->pluck('people.id'))
            ->whereKeyNot($this->benchOwner()->person->getKey())
            ->orderBy('id')
            ->limit(5)
            ->pluck('id')
            ->all();

        $this->measure('members bulk remove (5 rows)', fn () => Livewire::test(RemoveMembersModal::class)
            ->call('confirm', $ids)
            ->call('delete'));

        expect($this->bench()->members()->whereKey($ids)->exists())->toBeFalse();
    })->group('query-bulk');
});

describe('Actions', function (): void {
    beforeEach(function (): void {
        /** @var TestCase $this */
        $this->requireWriteQuery();
        $this->switchTo($this->benchOwner(), $this->bench());
    });

    it('creates a credentialed member', function (): void {
        /** @var TestCase $this */
        $name = 'Baseline Member '.$this->suffix();
        $email = 'member-'.$this->suffix().'@example.test';

        $this->measure('member create (credentialed)', fn () => Livewire::test(MemberForm::class)
            ->call('openCreate')
            ->set('name', $name)
            ->set('email', $email)
            ->set('role', Role::Member->value)
            ->set('createCredential', true)
            ->set('contactEmail', 'member-contact-'.$this->suffix().'@example.test')
            ->set('contactPhone', '081234567893')
            ->call('create'));

        $person = Person::query()->where('name', $name)->firstOrFail();

        expect($person->user?->email)->toBe($email)
            ->and($person->membershipIn($this->bench()))->not->toBeNull();
    })->group('query-write');

    it('creates a member without a credential', function (): void {
        /** @var TestCase $this */
        $name = 'Baseline Plain Member '.$this->suffix();

        $this->measure('member create (no credential)', fn () => Livewire::test(MemberForm::class)
            ->call('openCreate')
            ->set('name', $name)
            ->set('email', 'plain-'.$this->suffix().'@example.test')
            ->set('role', Role::Member->value)
            ->set('createCredential', false)
            ->call('create'));

        $person = Person::query()->where('name', $name)->firstOrFail();

        expect($person->user_id)->toBeNull()
            ->and($person->membershipIn($this->bench()))->not->toBeNull();
    })->group('query-write');

    it('updates a member role', function (): void {
        /** @var TestCase $this */
        $member = $this->benchMember('Member 2-4');
        $current = $member->membershipIn($this->bench())?->role;
        $role = $current === Role::Member ? Role::Admin : Role::Member;

        $this->measure('member update (role)', fn () => Livewire::test(MemberForm::class, ['person' => $member])
            ->set('role', $role->value)
            ->call('update'));

        expect($member->fresh()->membershipIn($this->bench())?->role)->toBe($role);
    })->group('query-write');

    it('removes a member', function (): void {
        /** @var TestCase $this */
        $member = $this->benchMember('Member 2-5');

        $this->measure('member remove', fn () => Livewire::test(RemoveMemberModal::class)
            ->call('confirm', $member->getKey())
            ->call('delete'));

        expect($member->fresh()->membershipIn($this->bench()))->toBeNull();
    })->group('query-write');
});
