<?php

declare(strict_types=1);

namespace App\Livewire\Notifications;

use App\Actions\Notifications\MarkNotificationsAsRead;
use App\Models\User;
use App\Repositories\NotificationRepository;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public bool $sortAscending = false;

    public ?string $openNotificationId = null;

    /**
     * @var array<int, string>
     */
    public array $selectedNotifications = [];

    public function mount(Request $request): void
    {
        $open = $request->query('open');

        $this->openNotificationId = \is_string($open) ? $open : null;
    }

    public function render(): View
    {
        /** @var User $user */
        $user = Auth::user();

        /** @disregard P1013 */
        return view('livewire.notifications.index', [
            'notifications' => app(NotificationRepository::class)->get($user, $this->sortAscending),
        ])->title(__('Notifications'));
    }

    /**
     * Refresh the inbox after a notification is opened.
     */
    #[On('notification-opened')]
    public function refreshNotifications(): void
    {
        //
    }

    /**
     * Toggle the received-date sort order and clear the selection.
     */
    public function sortByReceivedAt(): void
    {
        $this->sortAscending = ! $this->sortAscending;
        $this->selectedNotifications = [];

        $this->resetPage();
    }

    /**
     * Clear the selection when the page changes.
     */
    public function updatedPage(): void
    {
        $this->selectedNotifications = [];
    }

    /**
     * Add or remove the given notification from the selection.
     */
    public function toggleSelected(string $id): void
    {
        $this->selectedNotifications = \in_array($id, $this->selectedNotifications, true)
            ? array_values(array_diff($this->selectedNotifications, [$id]))
            : [...$this->selectedNotifications, $id];
    }

    /**
     * Select or deselect every notification on the page.
     */
    public function toggleSelectAll(): void
    {
        $ids = $this->notificationIdsOnPage();

        $this->selectedNotifications = array_diff($ids, $this->selectedNotifications) === []
            ? []
            : $ids;
    }

    /**
     * Mark the selected notifications as read.
     */
    public function markSelectedAsRead(): void
    {
        $this->markSelected(asRead: true);
    }

    /**
     * Mark the selected notifications as unread.
     */
    public function markSelectedAsUnread(): void
    {
        $this->markSelected(asRead: false);
    }

    /**
     * Mark the selected notifications with the given read state.
     */
    private function markSelected(bool $asRead): void
    {
        if ($this->selectedNotifications === []) {
            return;
        }

        /** @var User $user */
        $user = Auth::user();

        $count = app(MarkNotificationsAsRead::class)->handle($user, $this->selectedNotifications, $asRead);

        $this->selectedNotifications = [];

        Flux::toast(
            variant: 'success',
            text: $asRead
                ? __('Successfully mark :count notification as read', ['count' => $count])
                : __('Successfully mark :count notification as unread', ['count' => $count]),
        );

        $this->dispatchUnreadCount();
    }

    /**
     * Broadcast the user's current unread notification count.
     */
    private function dispatchUnreadCount(): void
    {
        /** @var User $user */
        $user = Auth::user();

        $this->dispatch('notifications-updated', count: app(NotificationRepository::class)->countUnread($user));
    }

    /**
     * @return array<int, string>
     */
    private function notificationIdsOnPage(): array
    {
        /** @var User $user */
        $user = Auth::user();

        return app(NotificationRepository::class)->getIdsOnPage($user, $this->sortAscending, $this->getPage());
    }
}
