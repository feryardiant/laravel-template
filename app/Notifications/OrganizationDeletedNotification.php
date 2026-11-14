<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrganizationDeletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $organizationName,
        public ?string $actorName = null,
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

        if ($this->isLastMembership($notifiable)) {
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
            'lines' => $this->isLastMembership($notifiable) ? [$this->lastMembershipLine()] : [],
            'salutation' => __('Best regards'),
        ];
    }

    private function subject(): string
    {
        return __('The organization :organization has been deleted', [
            'organization' => $this->organizationName,
        ]);
    }

    private function greeting(User $notifiable): string
    {
        return __('Hi :name', ['name' => $notifiable->name]);
    }

    private function description(): string
    {
        return $this->actorName === null
            ? __('The organization :organization has been deleted.', ['organization' => $this->organizationName])
            : __(':actor deleted the organization :organization.', [
                'actor' => $this->actorName,
                'organization' => $this->organizationName,
            ]);
    }

    private function lastMembershipLine(): string
    {
        return __('You are no longer a member of any organization.');
    }

    private function isLastMembership(User $notifiable): bool
    {
        return $notifiable->person?->organizations()->doesntExist() ?? true;
    }
}
