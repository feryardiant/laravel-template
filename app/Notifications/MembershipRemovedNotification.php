<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MembershipRemovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Organization $organization,
        public ?User $actor = null,
        public bool $lastMembership = false,
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
        $mail = (new MailMessage)
            ->subject($this->subject())
            ->greeting($this->greeting($notifiable))
            ->line($this->description());

        if ($this->lastMembership) {
            $mail->line($this->lastMembershipLine());
        }

        return $mail->salutation(__('Best regards'));
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
            'lines' => $this->lastMembership ? [$this->lastMembershipLine()] : [],
            'salutation' => __('Best regards'),
        ];
    }

    private function subject(): string
    {
        return $this->actor === null
            ? __('You left :organization', ['organization' => $this->organization->name])
            : __('You were removed from :organization', ['organization' => $this->organization->name]);
    }

    private function greeting(User $notifiable): string
    {
        return __('Hi :name', ['name' => $notifiable->name]);
    }

    private function description(): string
    {
        return $this->actor === null
            ? __('You left :organization.', ['organization' => $this->organization->name])
            : __(':actor removed you from :organization.', [
                'actor' => $this->actor->name,
                'organization' => $this->organization->name,
            ]);
    }

    private function lastMembershipLine(): string
    {
        return __('You are no longer a member of any organization.');
    }
}
