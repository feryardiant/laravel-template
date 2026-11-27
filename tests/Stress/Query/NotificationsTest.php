<?php

declare(strict_types=1);

use App\Livewire\Notifications\Index as NotificationIndex;
use App\Livewire\Notifications\Partials\NotificationModal;
use Livewire\Livewire;
use Tests\Stress\TestCase;

describe('Livewire pages', function (): void {
    beforeEach(function (): void {
        /** @var TestCase $this */
        $this->requireQuery();
        $this->switchTo($this->benchOwner(), $this->bench());
    });

    it('renders the notification inbox', function (): void {
        /** @var TestCase $this */
        $this->measure('bench notifications', fn () => Livewire::test(NotificationIndex::class), runs: 3);
    })->group('query-read');
});

describe('Actions', function (): void {
    beforeEach(function (): void {
        /** @var TestCase $this */
        $this->requireQuery();
        $this->switchTo($this->benchOwner(), $this->bench());
    });

    it('marks a notification as read', function (): void {
        /** @var TestCase $this */
        $notificationId = $this->benchOwner()->notifications()->whereNull('read_at')->value('id');

        $this->measure('notification mark read', fn () => Livewire::test(NotificationIndex::class)
            ->set('selectedNotifications', [$notificationId])
            ->call('markSelectedAsRead'));

        expect($this->benchOwner()->notifications()->whereKey($notificationId)->value('read_at'))->not->toBeNull();
    })->group('query-write');

    it('opens a notification', function (): void {
        /** @var TestCase $this */
        $notificationId = $this->benchOwner()->notifications()->whereNull('read_at')->value('id');

        $this->measure('notification open (mark one)', fn () => Livewire::test(NotificationModal::class)
            ->call('open', $notificationId));

        expect($this->benchOwner()->notifications()->whereKey($notificationId)->value('read_at'))->not->toBeNull();
    })->group('query-write');
});
