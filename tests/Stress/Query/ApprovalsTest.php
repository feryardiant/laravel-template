<?php

declare(strict_types=1);

use App\Actions\Approvals\OwnershipTransfer;
use App\Enums\ApprovalRequestStatus;
use App\Enums\Role;
use App\Livewire\OrgSettings\General\TransferOwnership;
use App\Livewire\Settings\Organizations\TransferApproval;
use App\Models\ApprovalRequest;
use App\Models\Person;
use App\Models\User;
use Livewire\Livewire;
use Tests\Stress\TestCase;

describe('Request and cancel', function (): void {
    beforeEach(function (): void {
        /** @var TestCase $this */
        $this->requireQuery();
        $this->switchTo($this->rootOwner(), $this->root());
    });

    it('requests an ownership transfer', function (): void {
        /** @var TestCase $this */
        ApprovalRequest::query()
            ->where('organization_id', $this->root()->getKey())
            ->where('status', ApprovalRequestStatus::Pending)
            ->update(['status' => ApprovalRequestStatus::Canceled, 'canceled_at' => now()]);

        $recipient = $this->root()->members()->whereKeyNot($this->rootOwner()->person->getKey())->whereHas('user')->firstOrFail();

        $this->measure('ownership transfer request', fn () => Livewire::test(TransferOwnership::class)
            ->set('recipientId', (string) $recipient->getKey())
            ->set('confirmation', $this->root()->name)
            ->call('transfer'));

        expect(ApprovalRequest::query()
            ->where('organization_id', $this->root()->getKey())
            ->where('status', 'pending')
            ->exists())->toBeTrue();
    })->group('query-write');

    it('cancels an ownership transfer', function (): void {
        /** @var TestCase $this */
        $recipient = $this->root()->members()->whereKeyNot($this->rootOwner()->person->getKey())->whereHas('user')->firstOrFail();

        if (! ApprovalRequest::query()->where('organization_id', $this->root()->getKey())->where('status', 'pending')->exists()) {
            app(OwnershipTransfer::class)->request($this->root(), $this->rootOwner(), $recipient);
        }

        $this->measure('ownership transfer cancel', fn () => Livewire::test(TransferOwnership::class)
            ->call('cancelTransfer'));

        expect(ApprovalRequest::query()
            ->where('organization_id', $this->root()->getKey())
            ->where('status', 'pending')
            ->exists())->toBeFalse();
    })->group('query-write');
});

describe('Accept and decline', function (): void {
    beforeEach(function (): void {
        /** @var TestCase $this */
        $this->requireQuery();
    });

    it('accepts an ownership transfer', function (): void {
        /** @var TestCase $this */
        $root = $this->root();
        $owner = $this->rootOwner();
        $recipient = $root->members()->whereKeyNot($owner->person->getKey())->whereHas('user')->firstOrFail();
        $recipientUser = $recipient->user;

        app(OwnershipTransfer::class)->request($root, $owner, $recipient);

        $this->switchTo($recipientUser, $root);

        $this->measure('ownership transfer accept', fn () => Livewire::test(TransferApproval::class)
            ->call('respond', $root->getKey())
            ->call('accept'));

        expect($root->fresh()->members()->wherePivot('role', Role::Owner->value)->value('people.id'))
            ->toBe($recipient->getKey());
    })->group('query-approval');

    it('declines an ownership transfer', function (): void {
        /** @var TestCase $this */
        $bench = $this->bench();
        $pending = ApprovalRequest::query()
            ->where('organization_id', $bench->getKey())
            ->where('status', ApprovalRequestStatus::Pending)
            ->firstOrFail();

        $approverUser = Person::query()->findOrFail($pending->approver_person_id)->user;
        \assert($approverUser instanceof User);

        $this->switchTo($approverUser, $bench);

        $this->measure('ownership transfer decline', fn () => Livewire::test(TransferApproval::class)
            ->call('respond', $bench->getKey())
            ->call('decline'));

        expect($pending->fresh()->status)->toBe(ApprovalRequestStatus::Declined);
    })->group('query-approval');
});
