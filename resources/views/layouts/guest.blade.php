@props([
    'pageTitle' => 'HealthLink',
    'heading' => 'Welcome to HealthLink',
    'description' => 'Secure municipal and barangay health records in one connected workflow.',
    'eyebrow' => 'Tubigon Health Records',
    'heroTitle' => 'Built for connected barangay care',
    'heroDescription' => 'HealthLink keeps local records, approvals, and field activity in one clear system for Tubigon health teams.',
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
        <div
            class="relative min-h-screen overflow-hidden bg-slate-950 bg-cover bg-center bg-no-repeat"
            style="background-image: url('{{ asset('images/healthlink_bg.jpg') }}');"
        >
            <div class="absolute inset-0 bg-gradient-to-t from-[rgba(0,63,127,0.94)] via-[rgba(0,63,127,0.56)] to-[rgba(0,63,127,0.18)]"></div>
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(255,255,255,0.18),_transparent_28%),radial-gradient(circle_at_bottom_right,_rgba(0,32,74,0.28),_transparent_30%)]"></div>
            <div class="absolute inset-y-0 left-0 hidden w-1/2 bg-[linear-gradient(160deg,_rgba(0,63,127,0.88),_rgba(0,45,92,0.94))] lg:block"></div>
            <div class="absolute -left-24 top-24 h-72 w-72 rounded-full bg-white/10 blur-3xl lg:block"></div>
            <div class="absolute bottom-12 left-1/3 hidden h-40 w-40 rounded-full border border-white/15 lg:block"></div>
            <div class="absolute right-8 top-12 h-36 w-36 rounded-full bg-white/10 blur-3xl"></div>

            <div class="relative mx-auto flex min-h-screen max-w-7xl items-center px-4 py-8 sm:px-6 lg:px-8">
                <div class="grid w-full gap-6 lg:grid-cols-[0.95fr_1.05fr]">
                    <section class="hidden min-h-[700px] overflow-hidden rounded-[36px] border border-white/10 bg-gradient-to-br from-tubigon to-tubigon-hover p-8 text-white shadow-2xl lg:flex lg:flex-col lg:justify-between">
                        <div>
                            <a href="{{ route('login') }}" class="inline-flex items-center gap-3">
                                <img src="{{ asset('images/tubigon-logo.png') }}" alt="Tubigon Logo" class="h-12 w-12 object-contain">
                                <div>
                                    <p class="text-xl font-bold tracking-tight">HealthLink</p>
                                    <p class="text-sm text-white/70">LGU Tubigon, Bohol</p>
                                </div>
                            </a>
                        </div>

                        <div class="max-w-lg">
                            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-white/60">{{ $eyebrow }}</p>
                            <h1 class="mt-6 text-4xl font-semibold tracking-tight leading-tight">{{ $heroTitle }}</h1>
                            <p class="mt-5 text-base leading-8 text-white/80">{{ $heroDescription }}</p>

                            <div class="mt-8 grid gap-3 text-sm text-white/85">
                                <div class="rounded-2xl border border-white/10 bg-white/8 px-4 py-3">
                                    Scope-aware access for admins, barangay supervisors, and field workers.
                                </div>
                                <div class="rounded-2xl border border-white/10 bg-white/8 px-4 py-3">
                                    Approval, sync, and audit-ready workflows for real local operations.
                                </div>
                                <div class="rounded-2xl border border-white/10 bg-white/8 px-4 py-3">
                                    A cleaner path from registration to records to community reporting.
                                </div>
                            </div>
                        </div>

                        <div class="text-sm text-white/65">
                            © {{ date('Y') }} HealthLink · Municipality of Tubigon
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-[32px] border border-white/60 bg-white/95 shadow-2xl shadow-slate-300/40 backdrop-blur">
                        <div class="border-b border-slate-200/80 px-6 py-5 lg:hidden">
                            <div class="flex items-center gap-3">
                                <img src="{{ asset('images/tubigon-logo.png') }}" alt="Tubigon Logo" class="h-10 w-10 object-contain">
                                <div>
                                    <p class="text-lg font-bold tracking-tight text-slate-900">HealthLink</p>
                                    <p class="text-sm text-slate-500">LGU Tubigon, Bohol</p>
                                </div>
                            </div>
                        </div>

                        <div class="px-6 py-8 sm:px-8 lg:px-10 lg:py-10">
                            <div class="max-w-2xl">
                                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-tubigon/70">{{ $eyebrow }}</p>
                                <h1 class="mt-3 text-3xl font-semibold tracking-tight text-slate-900">{{ $heading }}</h1>
                                <p class="mt-3 max-w-xl text-sm leading-7 text-slate-600">{{ $description }}</p>
                            </div>

                            <div class="mt-8">
                                {{ $slot }}
                            </div>
                        </div>

                        @isset($footer)
                            <div class="border-t border-slate-200/80 bg-slate-50/80 px-6 py-4 sm:px-8 lg:px-10">
                                {{ $footer }}
                            </div>
                        @endisset
                    </section>
                </div>
            </div>
        </div>
    </body>
</html>
