<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class MemberLeftNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Organization $organization,
        public string $memberName,
    ) {
        $this->afterCommit = true;
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, string>
     */
    public function toDatabase(User $notifiable): array
    {
        return [
            'title' => __('Member left :organization', ['organization' => $this->organization->name]),
            'greeting' => __('Hi :name', ['name' => $notifiable->name]),
            'description' => __(':name left :organization.', [
                'name' => $this->memberName,
                'organization' => $this->organization->name,
            ]),
            'salutation' => __('Best regards'),
        ];
    }
}
