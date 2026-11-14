<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class OwnershipTransferRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public string $organizationName;

    public string $ownerName;

    public string $expiresAt;

    public string $url;

    public function __construct(ApprovalRequest $request)
    {
        $this->organizationName = $request->organization->name;
        $this->ownerName = $request->requester->name;
        $this->expiresAt = $request->expires_at->translatedFormat('d M Y');
        $this->url = URL::temporarySignedRoute(
            'organizations.approval.link',
            $request->expires_at,
            ['approval' => $request->uuid],
        );

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
            ->action(__('Review transfer'), $this->url)
            ->line(__('This request expires on :date.', ['date' => $this->expiresAt]))
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
            'lines' => [__('This request expires on :date.', ['date' => $this->expiresAt])],
            'salutation' => __('Best regards'),
            'action' => [
                'label' => __('Review transfer'),
                'url' => $this->url,
            ],
        ];
    }

    private function subject(): string
    {
        return __('Ownership transfer request for :organization', [
            'organization' => $this->organizationName,
        ]);
    }

    private function greeting(User $notifiable): string
    {
        return __('Hi :name', ['name' => $notifiable->name]);
    }

    private function description(): string
    {
        return __(':owner wants to transfer ownership of :organization to you.', [
            'owner' => $this->ownerName,
            'organization' => $this->organizationName,
        ]);
    }
}
