<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>HealthLink - Login</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased">
    <!-- Full screen background with overlay -->
    <div class="relative min-h-screen bg-cover bg-center bg-no-repeat flex items-center justify-center"
         style="background-image: url('{{ asset('images/healthlink_bg.jpg') }}');">
        
        <!-- Gradient Overlay -->
        <div class="absolute inset-0 bg-gradient-to-b from-transparent via-[#003f7f]/30 to-[#003f7f]/90"></div>

        <!-- Login Modal -->
        <div class="relative z-10 w-full max-w-md px-6">
            <div class="bg-white rounded-xl shadow-2xl overflow-hidden">
                <!-- Header with Dynamic Logo -->
                <div class="px-8 pt-8 pb-6 text-center border-b border-gray-100">
                    <div class="flex items-center justify-center space-x-2 mb-2">
                        <x-logo size="md" />
                    </div>
                    <p class="text-sm text-gray-500">Municipality of Tubigon · Barangay Health Records</p>
                </div>

                <!-- Form -->
                <div class="px-8 py-6">
                    <!-- Session Status -->
                    @if(session('status'))
                        <div class="mb-4 text-sm text-green-600">
                            {{ session('status') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login') }}">
                        @csrf

                        <!-- Email -->
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                                   class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('email') border-red-500 @enderror">
                            @error('email')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Password -->
                        <div class="mt-4">
                            <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                            <input id="password" type="password" name="password" required
                                   class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('password') border-red-500 @enderror">
                            @error('password')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Remember Me & Forgot Password -->
                        <div class="flex items-center justify-between mt-4">
                            <label class="flex items-center">
                                <input type="checkbox" name="remember" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                                <span class="ml-2 text-sm text-gray-600">Remember me</span>
                            </label>
                            @if(Route::has('password.request'))
                                <a href="{{ route('password.request') }}" class="text-sm text-blue-600 hover:text-blue-900">
                                    Forgot password?
                                </a>
                            @endif
                        </div>

                        <!-- Submit -->
                        <button type="submit" class="mt-6 w-full flex justify-center py-2.5 px-4 border border-transparent rounded-lg text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                            Sign in
                        </button>
                    </form>

                    <!-- Register Link -->
                    <p class="mt-4 text-center text-sm text-gray-600">
                        Don't have an account?
                        <a href="{{ route('register') }}" class="text-blue-600 hover:text-blue-900 font-medium">
                            Register here
                        </a>
                    </p>
                </div>

                <!-- Footer -->
                <div class="px-8 py-4 bg-gray-50 border-t border-gray-100 text-center">
                    <p class="text-xs text-gray-500">
                        © {{ date('Y') }} HealthLink · LGU Tubigon, Bohol
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>