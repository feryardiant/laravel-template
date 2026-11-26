<?php

declare(strict_types=1);

namespace App\Livewire\Notifications\Partials;

use App\Actions\Notifications\MarkNotificationsAsRead;
use App\Models\User;
use App\Repositories\NotificationRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Shows one notification in a flyout and marks it read.
 *
 * @property-read ?DatabaseNotification $selectedNotification
 */
class NotificationModal extends Component
{
    public bool $showModal = false;

    public ?string $notificationId = null;

    /**
     * Open the notification passed by the inbox query string.
     */
    public function mount(?string $notificationId = null): void
    {
        if ($notificationId !== null && $notificationId !== '') {
            $this->open($notificationId);
        }
    }

    public function render(): View
    {
        return view('livewire.notifications.partials.notification-modal');
    }

    /**
     * Open the notification, mark it read, and focus the flyout.
     */
    #[On('open-notification')]
    public function open(string $id): void
    {
        /** @var User $user */
        $user = Auth::user();

        $notification = app(NotificationRepository::class)->find($user, $id);

        if ($notification === null) {
            return;
        }

        app(MarkNotificationsAsRead::class)->markOne($user, $id);

        $this->notificationId = $id;
        $this->showModal = true;

        $this->dispatch('notifications-updated', count: app(NotificationRepository::class)->countUnread($user));
        $this->dispatch('notification-opened');

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
     * The notification open in the flyout.
     */
    #[Computed]
    public function selectedNotification(): ?DatabaseNotification
    {
        if ($this->notificationId === null) {
            return null;
        }

        /** @var User $user */
        $user = Auth::user();

        return app(NotificationRepository::class)->find($user, $this->notificationId);
    }
}
