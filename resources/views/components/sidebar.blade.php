@props(['active' => false])

<div class="hidden sm:flex sm:flex-col sm:w-64 sm:fixed sm:inset-y-0">
    <div class="flex flex-col flex-1 min-h-0 bg-white border-r border-gray-200">
        <div class="flex items-center flex-shrink-0 h-16 px-4">
            <a href="{{ route('admin.dashboard') }}" class="text-xl font-bold text-blue-600">
                HealthLink
            </a>
        </div>
        <div class="flex flex-col flex-1 overflow-y-auto">
            <nav class="flex-1 px-2 py-4 space-y-1">
                <x-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">
                    Dashboard
                </x-nav-link>
                
                <div class="pt-4 mt-4 border-t border-gray-200">
                    <p class="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Management</p>
                    
                    <x-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">
                        Users
                    </x-nav-link>
                    
                    <x-nav-link :href="route('admin.barangays.index')" :active="request()->routeIs('admin.barangays.*')">
                        Barangays
                    </x-nav-link>
                    
                    <x-nav-link :href="route('admin.puroks.index')" :active="request()->routeIs('admin.puroks.*')">
                        Puroks
                    </x-nav-link>
                </div>

                <div class="pt-4 mt-4 border-t border-gray-200">
                    <p class="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">System</p>
                    
                    <x-nav-link :href="route('admin.audit.index')" :active="request()->routeIs('admin.audit.*')">
                        Audit Trail
                    </x-nav-link>
                    
                    <x-nav-link :href="route('admin.devices.index')" :active="request()->routeIs('admin.devices.*')">
                        Mobile Devices
                    </x-nav-link>
                    
                    <x-nav-link :href="route('admin.backups.index')" :active="request()->routeIs('admin.backups.*')">
                        Backups
                    </x-nav-link>
                    
                    <x-nav-link :href="route('admin.metrics.index')" :active="request()->routeIs('admin.metrics.*')">
                        System Metrics
                    </x-nav-link>
                    
                    <x-nav-link :href="route('admin.rate-limits.index')" :active="request()->routeIs('admin.rate-limits.*')">
                        Rate Limits
                    </x-nav-link>
                </div>
            </nav>
        </div>
    </div>
</div>