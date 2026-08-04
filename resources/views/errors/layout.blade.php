<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'HealthLink') · HealthLink</title>
</head>
<body style="margin: 0; min-width: 320px; background: #003f7f; color: #172033; font-family: Arial, Helvetica, sans-serif;">
    <main style="min-height: 100vh; box-sizing: border-box; padding: 32px 20px; background: linear-gradient(0deg, rgba(0, 50, 105, 0.93), rgba(0, 92, 172, 0.64)), url('{{ asset('images/healthlink_bg.jpg') }}') center / cover no-repeat;">
        <section style="width: 100%; max-width: 620px; margin: 0 auto; padding-top: min(18vh, 150px); text-align: center;">
            <img src="{{ asset('images/tubigon-logo.png') }}" alt="Municipality of Tubigon" style="width: 88px; height: 88px; object-fit: contain; filter: drop-shadow(0 10px 18px rgba(0, 24, 60, 0.34));">
            <p style="margin: 18px 0 0; color: rgba(255, 255, 255, 0.74); font-size: 12px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase;">Municipality of Tubigon</p>
            <p style="margin: 8px 0 0; color: #ffffff; font-size: 34px; font-weight: 700; letter-spacing: -0.8px;">HealthLink</p>

            <article style="margin-top: 32px; padding: 36px 30px; border-radius: 12px; background: #ffffff; box-shadow: 0 18px 46px rgba(0, 24, 60, 0.3);">
                <p style="margin: 0; color: #4775a5; font-size: 12px; font-weight: 700; letter-spacing: 1.8px; text-transform: uppercase;">@yield('code')</p>
                <h1 style="margin: 14px 0 0; color: #172033; font-size: 27px; line-height: 35px;">@yield('heading')</h1>
                <p style="margin: 14px auto 0; max-width: 460px; color: #53657d; font-size: 15px; line-height: 24px;">@yield('message')</p>
                <div style="margin-top: 28px;">
                    <a href="{{ route('login') }}" style="display: inline-block; padding: 12px 20px; border-radius: 7px; background: #0058ad; color: #ffffff; font-size: 14px; font-weight: 700; text-decoration: none;">Return to HealthLink</a>
                </div>
            </article>
        </section>
    </main>
</body>
</html>
