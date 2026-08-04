<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends BaseResetPassword implements ShouldQueue
{
    use Queueable;

    /**
     * Build the HealthLink password reset message.
     */
    public function toMail($notifiable): MailMessage
    {
        $expiresInMinutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);
        $resetUrl = $this->resetUrl($notifiable);
        $viewData = [
            'user' => $notifiable,
            'resetUrl' => $resetUrl,
            'expiresInMinutes' => $expiresInMinutes,
        ];

        return (new MailMessage)
            ->subject('Reset your HealthLink password')
            ->view('mail.auth.reset-password', $viewData)
            ->text('mail.auth.reset-password-text', $viewData);
    }
}
