@extends('layouts.admin')

@section('title', 'Reset Password - HealthLink Admin')
@section('header', 'Reset Password for ' . $user->name)

@section('actions')
    <a href="{{ route('admin.users.index') }}" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">
        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        Back
    </a>
@endsection

@section('content')
    @if(session('temporary_password'))
        <div class="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-4">
            <p class="text-sm font-semibold text-amber-900">One-Time Temporary Password</p>
            <p class="mt-1 text-sm text-amber-800">
                This value is shown only on this page load. Copy it now and share it with the user through a secure channel.
            </p>
            <div class="mt-3 rounded-md bg-white px-4 py-3 font-mono text-lg tracking-wide text-gray-900">
                {{ session('temporary_password') }}
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <!-- Manual Reset -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Set Custom Password</h3>
            </div>
            <div class="p-6">
                <form method="POST" action="{{ route('admin.users.password.reset', $user) }}">
                    @csrf
                    @method('PUT')

                    <div class="space-y-4">
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700">New Password</label>
                            <input type="password" name="password" id="password" 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('password') border-red-500 @enderror"
                                   required>
                            @error('password')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm Password</label>
                            <input type="password" name="password_confirmation" id="password_confirmation" 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                   required>
                        </div>
                    </div>

                    <div class="mt-6">
                        <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">
                            Set Password
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Generate Temporary Password -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Generate Temporary Password</h3>
            </div>
            <div class="p-6">
                <p class="text-sm text-gray-600 mb-4">
                    Generate a random temporary password for the user. The password will be shown once so an admin can hand it off securely.
                </p>
                
                <form method="POST" action="{{ route('admin.users.password.generate', $user) }}">
                    @csrf
                    
                    <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-yellow-600 rounded-md hover:bg-yellow-700">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                        </svg>
                        Generate Temporary Password
                    </button>
                </form>

                <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-md">
                    <p class="text-xs text-yellow-700">
                        <strong>Note:</strong> This will overwrite the current password immediately. Make sure the user receives the temporary password through a secure channel.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
