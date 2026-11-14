<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OwnershipTransferDeclinedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $organizationName,
        public string $recipientName,
    ) {
        $this->afterCommit = true;
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subject())
            ->greeting($this->greeting($notifiable))
            ->line($this->description())
            ->salutation(__('Best regards'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(User $notifiable): array
    {
        return [
            'title' => $this->subject(),
            'greeting' => $this->greeting($notifiable),
            'description' => $this->description(),
            'lines' => [],
            'salutation' => __('Best regards'),
        ];
    }

    private function subject(): string
    {
        return __('Ownership transfer for :organization declined', ['organization' => $this->organizationName]);
    }

    private function greeting(User $notifiable): string
    {
        return __('Hi :name', ['name' => $notifiable->name]);
    }

    private function description(): string
    {
        return __(':name declined ownership of :organization.', [
            'name' => $this->recipientName,
            'organization' => $this->organizationName,
        ]);
    }
}
