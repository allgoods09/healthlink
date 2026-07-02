@extends('layouts.admin')

@section('title', 'Create Purok - HealthLink Admin')
@section('header', 'Create New Purok')

@section('actions')
    <a href="{{ route('admin.puroks.index') }}" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">
        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        Back
    </a>
@endsection

@section('content')
    <div class="bg-white rounded-lg shadow">
        <div class="p-6">
            <form method="POST" action="{{ route('admin.puroks.store') }}">
                @csrf

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <!-- Barangay -->
                    <div>
                        <label for="barangay_id" class="block text-sm font-medium text-gray-700">Barangay</label>
                        <select name="barangay_id" id="barangay_id" 
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('barangay_id') border-red-500 @enderror"
                                required>
                            <option value="">Select Barangay</option>
                            @foreach($barangays as $barangay)
                                <option value="{{ $barangay->id }}" {{ old('barangay_id', request('barangay_id')) == $barangay->id ? 'selected' : '' }}>
                                    {{ $barangay->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('barangay_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Purok Number -->
                    <div>
                        <label for="purok_number" class="block text-sm font-medium text-gray-700">Purok Number</label>
                        <input type="number" name="purok_number" id="purok_number" value="{{ old('purok_number') }}" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('purok_number') border-red-500 @enderror"
                               placeholder="e.g., 1, 2, 3"
                               required>
                        @error('purok_number')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Purok Name (Optional) -->
                    <div>
                        <label for="purok_name" class="block text-sm font-medium text-gray-700">Purok Name <span class="text-gray-500 text-xs">(optional)</span></label>
                        <input type="text" name="purok_name" id="purok_name" value="{{ old('purok_name') }}" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('purok_name') border-red-500 @enderror"
                               placeholder="e.g., Purok Kabataang Barangay">
                        @error('purok_name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Is Active -->
                    <div class="flex items-center">
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="is_active" value="1" checked
                                   class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                            <span class="ml-2 text-sm text-gray-700">Active</span>
                        </label>
                    </div>
                </div>

                <!-- Info Box -->
                <div class="mt-6 p-3 bg-blue-50 border border-blue-200 rounded-md">
                    <p class="text-sm text-blue-700">
                        <strong>Note:</strong> Each barangay can have only one Purok with the same number. 
                        For example, Purok 1 can only exist once per barangay.
                    </p>
                </div>

                <div class="mt-6 flex items-center justify-end">
                    <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Create Purok
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection