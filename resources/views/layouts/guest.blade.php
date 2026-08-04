@props([
    'pageTitle' => 'HealthLink',
    'heading' => 'Welcome to HealthLink',
    'description' => 'Secure municipal and barangay health records in one connected workflow.',
    'eyebrow' => 'Tubigon Health Records',
    'heroTitle' => 'Built for connected barangay care',
    'heroDescription' => 'HealthLink keeps local records, approvals, and field activity in one clear system for Tubigon health teams.',
    'contentWidth' => 'max-w-2xl',
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $pageTitle }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-950 font-sans text-slate-900 antialiased">
        <x-flash-toasts />
        <x-action-confirmation-modal />
        <div
            class="relative min-h-screen overflow-hidden bg-slate-950 bg-cover bg-center bg-no-repeat"
            style="background-image: url('{{ asset('images/healthlink_bg.jpg') }}');"
        >
            <div class="absolute inset-0 bg-gradient-to-t from-[rgba(0,63,127,0.94)] via-[rgba(0,63,127,0.56)] to-[rgba(0,63,127,0.18)]"></div>
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(255,255,255,0.16),_transparent_28%),radial-gradient(circle_at_bottom_right,_rgba(0,32,74,0.24),_transparent_30%)]"></div>

            <div class="relative mx-auto flex min-h-screen items-center justify-center px-4 py-8 sm:px-6 lg:px-8">
                <div class="w-full {{ $contentWidth }}">
                    <div class="mb-6 text-center text-white">
                        <a href="{{ route('login') }}" class="inline-flex flex-col items-center gap-4">
                            <img src="{{ asset('images/tubigon-logo.png') }}" alt="Tubigon Logo" class="h-20 w-20 object-contain drop-shadow-xl sm:h-24 sm:w-24">
                            <div>
                                <p class="text-3xl font-bold tracking-tight sm:text-4xl">HealthLink</p>
                                <p class="mt-2 text-sm font-medium text-white/80">Municipality of Tubigon · Barangay Health Records</p>
                            </div>
                        </a>
                    </div>

                    <section class="overflow-hidden rounded-xl bg-white shadow-2xl">
                        <div class="border-b border-slate-200 px-8 pt-8 pb-6 text-center">
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">{{ $eyebrow }}</p>
                            <h1 class="mt-3 text-2xl font-semibold tracking-tight text-slate-900">{{ $heading }}</h1>
                            <p class="mt-3 text-sm leading-7 text-slate-600">{{ $description }}</p>
                        </div>

                        <div class="px-8 py-6">
                            {{ $slot }}
                        </div>

                        @isset($footer)
                            <div class="border-t border-slate-200 bg-slate-50 px-8 py-4">
                                {{ $footer }}
                            </div>
                        @endisset
                    </section>

                    <div class="mt-5 text-center text-xs text-white/75">
                        © {{ date('Y') }} HealthLink · Municipality of Tubigon
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
