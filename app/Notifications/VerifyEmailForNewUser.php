<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class VerifyEmailForNewUser extends VerifyEmail
{
    /**
     * Include the generated username so the invitee can log in.
     *
     * @param  User  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Verify your email address'))
            ->line(__('Your username is :handle.', ['handle' => $notifiable->handle]))
            ->line(__('Click the button below to verify your email address.'))
            ->action(__('Verify email address'), $this->verificationUrl($notifiable))
            ->line(__('If you did not create an account, no further action is required.'));
    }

    /**
     * Use the public verification link, which ends on the reset password page.
     */
    protected function verificationUrl($notifiable): string
    {
        return URL::temporarySignedRoute(
            'verification.verify.new',
            Carbon::now()->addMinutes(config('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );
    }
}
