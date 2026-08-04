@include('mail.partials.header', [
    'title' => 'Verify your HealthLink email address',
    'preheader' => 'Confirm your email to continue the HealthLink account setup process.',
])

<h1 style="margin: 0; color: #172033; font-size: 25px; line-height: 32px;">Verify your email address</h1>

<p style="margin: 20px 0 0; color: #415168; font-size: 15px; line-height: 24px;">
    Hello {{ $user->display_name }},
</p>

<p style="margin: 14px 0 0; color: #415168; font-size: 15px; line-height: 24px;">
    Confirm that this email address belongs to you before HealthLink can continue your account setup. After verification, frontline accounts still need the appropriate barangay approval and assignment.
</p>

<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 28px 0;">
    <tr>
        <td style="border-radius: 7px; background: #0058ad;">
            <a href="{{ $verificationUrl }}" style="display: inline-block; padding: 13px 22px; color: #ffffff; font-size: 15px; font-weight: 700; line-height: 20px; text-decoration: none;">Verify Email Address</a>
        </td>
    </tr>
</table>

<p style="margin: 0; color: #5b6b80; font-size: 13px; line-height: 21px;">
    This verification link expires in {{ $expiresInMinutes }} minutes. If you did not request a HealthLink account, no further action is required.
</p>

@include('mail.partials.footer')
