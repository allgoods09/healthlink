<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailNotification extends BaseVerifyEmail implements ShouldQueue
{
    use Queueable;

    /**
     * Build the HealthLink email verification message.
     */
    public function toMail($notifiable): MailMessage
    {
        $expiresInMinutes = (int) config('auth.verification.expire', 60);
        $verificationUrl = $this->verificationUrl($notifiable);
        $viewData = [
            'user' => $notifiable,
            'verificationUrl' => $verificationUrl,
            'expiresInMinutes' => $expiresInMinutes,
        ];

        return (new MailMessage)
            ->subject('Verify your HealthLink email address')
            ->view('mail.auth.verify-email', $viewData)
            ->text('mail.auth.verify-email-text', $viewData);
    }
}
