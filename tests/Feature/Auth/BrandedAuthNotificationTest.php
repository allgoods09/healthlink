<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandedAuthNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_uses_the_healthlink_email_template(): void
    {
        $user = User::factory()->unverified()->create();
        $message = (new VerifyEmailNotification)->toMail($user);

        $this->assertSame('Verify your HealthLink email address', $message->subject);
        $this->assertSame([
            'html' => 'mail.auth.verify-email',
            'text' => 'mail.auth.verify-email-text',
        ], $message->view);

        $rendered = view($message->view['html'], $message->viewData)->render();

        $this->assertStringContainsString('Municipality of Tubigon', $rendered);
        $this->assertStringContainsString('Verify Email Address', $rendered);
        $this->assertStringContainsString('Hello '.$user->display_name, $rendered);
    }

    public function test_password_reset_uses_the_healthlink_email_template(): void
    {
        $user = User::factory()->create();
        $message = (new ResetPasswordNotification('test-token'))->toMail($user);

        $this->assertSame('Reset your HealthLink password', $message->subject);
        $this->assertSame([
            'html' => 'mail.auth.reset-password',
            'text' => 'mail.auth.reset-password-text',
        ], $message->view);

        $rendered = view($message->view['html'], $message->viewData)->render();

        $this->assertStringContainsString('Municipality of Tubigon', $rendered);
        $this->assertStringContainsString('Reset Password', $rendered);
        $this->assertStringContainsString('Hello '.$user->display_name, $rendered);
    }
}
