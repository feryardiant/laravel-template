<?php

declare(strict_types=1);

namespace App\Livewire\Notifications;

use App\Actions\Notifications\MarkNotificationsAsRead;
use App\Models\User;
use App\Repositories\NotificationRepository;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * @property-read ?DatabaseNotification $selectedNotification
 */
class Index extends Component
{
    use WithPagination;

    public bool $sortAscending = false;

    public bool $showNotificationModal = false;

    public ?string $selectedNotificationId = null;

    /**
     * @var array<int, string>
     */
    public array $selectedNotifications = [];

    public function mount(Request $request): void
    {
        $open = $request->query('open');

        if (! \is_string($open)) {
            return;
        }

        $this->openNotification($open);
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
     * The notification open in the detail modal.
     */
    #[Computed]
    public function selectedNotification(): ?DatabaseNotification
    {
        if ($this->selectedNotificationId === null) {
            return null;
        }

        /** @var User $user */
        $user = Auth::user();

        return app(NotificationRepository::class)->find($user, $this->selectedNotificationId);
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
     * Open the notification, mark it read, and focus its modal.
     */
    public function openNotification(string $id): void
    {
        /** @var User $user */
        $user = Auth::user();

        $notification = app(NotificationRepository::class)->find($user, $id);

        if ($notification === null) {
            return;
        }

        app(MarkNotificationsAsRead::class)->markOne($user, $id);

        $this->selectedNotificationId = $id;
        $this->showNotificationModal = true;

        $this->dispatchUnreadCount();

        $this->js(<<<'JS'
            const trapNotificationModalFocus = (dialog) => {
                if (dialog.dataset.focusTrapped) {
                    return;
                }

                dialog.dataset.focusTrapped = 'true';

                dialog.addEventListener('keydown', (event) => {
                    if (event.key !== 'Tab') {
                        return;
                    }

                    const focusable = Array.from(dialog.querySelectorAll(
                        'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
                    )).filter((element) => element.offsetParent !== null);

                    if (focusable.length === 0) {
                        event.preventDefault();

                        return;
                    }

                    const first = focusable[0];
                    const last = focusable[focusable.length - 1];

                    if (event.shiftKey && document.activeElement === first) {
                        event.preventDefault();
                        last.focus();
                    } else if (! event.shiftKey && document.activeElement === last) {
                        event.preventDefault();
                        first.focus();
                    }
                });
            };

            const focusNotificationModal = () => {
                const dialog = document.querySelector('dialog[data-flux-flyout][open]');

                if (! dialog) {
                    return false;
                }

                const title = dialog.querySelector('[data-notification-modal-title]');

                if (title) {
                    title.id ||= 'notification-modal-title';
                    dialog.setAttribute('aria-labelledby', title.id);
                }

                trapNotificationModalFocus(dialog);

                dialog.querySelector('[data-flux-modal-close] button')?.focus();

                return true;
            };

            if (! focusNotificationModal()) {
                setTimeout(focusNotificationModal, 60);
            }
        JS);
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
