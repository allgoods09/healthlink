<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>HealthLink Tubigon</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body x-data="{ formType: 'login' }" 
      class="font-sans antialiased bg-cover bg-center bg-no-repeat bg-fixed min-h-screen selection:bg-[#003f7f] selection:text-white"
      style="background-image: url('{{ asset('images/healthlink_bg.jpg') }}');">

    <div class="fixed inset-0 bg-gradient-to-b from-transparent via-[#003f7f]/30 to-[#003f7f]/90 z-0"></div>

    <div class="relative z-10 flex flex-col justify-between min-h-screen">
        
        <header class="sticky top-0 z-50 w-full bg-white/90 backdrop-blur-sm border-b border-gray-200/80 shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
                
                <div class="flex items-center space-x-4">
                    <img src="{{ asset('images/tubigon-logo.png') }}" alt="Tubigon Logo" class="w-12 h-12 object-contain drop-shadow-sm">
                    <div>
                        <span class="text-2xl font-black text-[#003f7f] tracking-tight">HealthLink</span>
                        <span class="text-2xl font-medium text-gray-500 mx-1.5"></span>
                        <span class="text-xl font-bold text-gray-700 tracking-wide uppercase">Tubigon</span>
                    </div>
                </div>

                <nav class="flex items-center space-x-3">
                    <a href="#auth-card" 
                       @click="formType = 'login'" 
                       class="px-4 py-2 text-sm font-semibold text-[#003f7f] hover:text-[#002d5c] hover:bg-[#003f7f]/5 rounded-lg transition">
                        Log In
                    </a>
                    <a href="#auth-card" 
                       @click="formType = 'register'" 
                       class="px-5 py-2.5 text-sm font-bold text-white bg-[#003f7f] hover:bg-[#002d5c] rounded-lg shadow-md transition transform active:scale-95">
                        Register
                    </a>
                </nav>
            </div>
        </header>

        <main class="flex-1 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col justify-center text-center pt-12 pb-24">
            <div class="max-w-3xl mx-auto space-y-6">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-white/20 text-white backdrop-blur-md border border-white/30 uppercase tracking-widest">
                    Official Municipal Portal
                </span>
                <h1 class="text-4xl sm:text-6xl font-black text-white tracking-tight drop-shadow-md">
                    Connecting Tubigon to Better Healthcare
                </h1>
                <p class="text-lg sm:text-xl text-gray-100 drop-shadow font-medium leading-relaxed">
                    Welcome to HealthLink, the unified information ecosystem of the Municipality of Tubigon. Empowering local health workers, managing community assignments, and tracking public health metrics seamlessly.
                </p>
                <div class="pt-4">
                    <a href="#auth-card" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-[#003f7f] bg-white hover:bg-gray-50 shadow-lg hover:shadow-xl transition-all">
                        Access Portal Dashboard
                        <svg class="ml-2 -mr-1 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                    </a>
                </div>
            </div>
        </main>

        <section id="auth-card" class="pb-32 pt-12 px-4 sm:px-6 lg:px-8">
            <div class="max-w-md mx-auto bg-white rounded-2xl shadow-2xl overflow-hidden border border-gray-100">
                
                <div class="flex border-b border-gray-100 bg-gray-50/50">
                    <button @click="formType = 'login'" 
                            :class="formType === 'login' ? 'border-b-2 border-[#003f7f] text-[#003f7f] bg-white font-bold' : 'text-gray-400 hover:text-gray-600'"
                            class="flex-1 py-4 text-center text-sm font-medium transition">
                        Account Login
                    </button>
                    <button @click="formType = 'register'" 
                            :class="formType === 'register' ? 'border-b-2 border-[#003f7f] text-[#003f7f] bg-white font-bold' : 'text-gray-400 hover:text-gray-600'"
                            class="flex-1 py-4 text-center text-sm font-medium transition">
                        Register Request
                    </button>
                </div>

                <div class="p-8">
                    <div x-show="formType === 'login'" x-transition:enter="transition ease-out duration-200" class="space-y-6">
                        <div>
                            <h2 class="text-xl font-bold text-gray-900">Welcome Back</h2>
                            <p class="text-xs text-gray-500 mt-1">Sign in with your municipality authorized credentials.</p>
                        </div>

                        <form method="POST" action="{{ route('login') }}" class="space-y-4">
                            @csrf
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Email Address</label>
                                <input type="email" name="email" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#003f7f] focus:border-[#003f7f] transition">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Password</label>
                                <input type="password" name="password" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#003f7f] focus:border-[#003f7f] transition">
                            </div>
                            <div class="flex items-center justify-between pt-1">
                                <label class="flex items-center text-sm text-gray-600">
                                    <input type="checkbox" name="remember" class="rounded text-[#003f7f] focus:ring-[#003f7f] border-gray-300 mr-2">
                                    Remember me
                                </label>
                                <a href="#" class="text-xs font-medium text-[#003f7f] hover:underline">Forgot password?</a>
                            </div>
                            <button type="submit" class="w-full py-3 bg-[#003f7f] hover:bg-[#002d5c] text-white font-bold rounded-lg shadow-lg transition">
                                Sign In
                            </button>
                        </form>
                    </div>

                    <div x-show="formType === 'register'" x-transition:enter="transition ease-out duration-200" class="space-y-6" x-cloak>
                        <div>
                            <h2 class="text-xl font-bold text-gray-900">Account Registration</h2>
                            <p class="text-xs text-gray-500 mt-1">Submit your details to request system credentials.</p>
                        </div>

                        <form method="POST" action="{{ route('register') }}" class="space-y-4">
                            @csrf
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Full Name</label>
                                <input type="text" name="name" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#003f7f] focus:border-[#003f7f] transition">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Email Address</label>
                                <input type="email" name="email" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#003f7f] focus:border-[#003f7f] transition">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Password</label>
                                <input type="password" name="password" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#003f7f] focus:border-[#003f7f] transition">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Confirm Password</label>
                                <input type="password" name="password_confirmation" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#003f7f] focus:border-[#003f7f] transition">
                            </div>
                            <button type="submit" class="w-full py-3 bg-[#003f7f] hover:bg-[#002d5c] text-white font-bold rounded-lg shadow-lg transition pt-2">
                                Submit Registration
                            </button>
                        </form>
                    </div>

                </div>
            </div>
        </section>
        <footer class="w-full bg-[#003f7f] border-t border-white/10 relative z-10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div class="flex flex-col md:flex-row items-center justify-between gap-4 text-center md:text-left">
                    
                    <div class="text-white/80 text-sm font-medium">
                        <p>&copy; {{ date('Y') }} HealthLink - Municipality of Tubigon, Bohol. All Rights Reserved.</p>
                        <p class="text-xs text-white/50 mt-1">Official Public Health Management & Information Ecosystem.</p>
                    </div>

                    <div class="flex items-center space-x-6 text-xs font-semibold text-white/70">
                        <a href="#" class="hover:text-white transition">Privacy Policy</a>
                        <a href="#" class="hover:text-white transition">Terms of Service</a>
                        <a href="#" class="hover:text-white transition">Support Desk</a>
                    </div>
                    
                </div>
            </div>
        </footer>
    </div>

</body>
</html>