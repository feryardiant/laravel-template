<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifies the members when an organization is deleted.
 */
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

    /**
     * The notification title and mail subject.
     */
    private function subject(): string
    {
        return __('The organization :organization has been deleted', [
            'organization' => $this->organizationName,
        ]);
    }

    /**
     * The personalized greeting for the recipient.
     */
    private function greeting(User $notifiable): string
    {
        return __('Hi :name', ['name' => $notifiable->name]);
    }

    /**
     * The body line describing what happened.
     */
    private function description(): string
    {
        return $this->actorName === null
            ? __('The organization :organization has been deleted.', ['organization' => $this->organizationName])
            : __(':actor deleted the organization :organization.', [
                'actor' => $this->actorName,
                'organization' => $this->organizationName,
            ]);
    }

    /**
     * The line shown when the recipient is left without an organization.
     */
    private function lastMembershipLine(): string
    {
        return __('You are no longer a member of any organization.');
    }

    /**
     * Whether the recipient has no other organization memberships.
     */
    private function isLastMembership(User $notifiable): bool
    {
        return $notifiable->person?->organizations()->doesntExist() ?? true;
    }
}
