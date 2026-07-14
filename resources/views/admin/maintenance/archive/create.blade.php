@extends('layouts.admin')

@section('title', 'Archive Record - HealthLink Admin')
@section('header', 'Archive Record')

@section('actions')
    <a href="{{ route('admin.archive.index') }}" class="inline-flex items-center rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">
        Back to Archive
    </a>
@endsection

@section('content')
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3" x-data="archiveSearch('{{ route('admin.archive.search') }}')">
        <div class="rounded-lg bg-white shadow xl:col-span-2">
            <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                <h2 class="text-lg font-medium text-gray-900">Find a Record</h2>
            </div>
            <div class="space-y-4 p-6">
                <p class="text-sm text-gray-600">
                    Search for a live record, review the match, and then archive it. Records already archived are hidden from search results.
                </p>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label for="table" class="block text-sm font-medium text-gray-700">Table</label>
                        <select id="table" x-model="table" @change="resetResults()" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Select a table</option>
                            @foreach($tables as $key => $modelClass)
                                <option value="{{ $key }}">{{ ucfirst($key) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700">Search Term</label>
                        <div class="mt-1 flex gap-2">
                            <input id="search" type="text" x-model="search" @keydown.enter.prevent="runSearch()" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Enter at least 2 characters">
                            <button type="button" @click="runSearch()" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Search</button>
                        </div>
                    </div>
                </div>

                <div x-show="message" class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800" x-text="message"></div>

                <div class="overflow-hidden rounded-lg border border-gray-200" x-show="results.length > 0">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Record</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Details</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <template x-for="result in results" :key="result.id">
                                <tr>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900" x-text="result.label"></td>
                                    <td class="px-4 py-3 text-sm text-gray-600" x-text="result.details"></td>
                                    <td class="px-4 py-3 text-right">
                                        <button type="button" @click="selectResult(result)" class="text-sm font-medium text-blue-600 hover:text-blue-900">Use This</button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="rounded-lg bg-white shadow">
            <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                <h2 class="text-lg font-medium text-gray-900">Archive Request</h2>
            </div>
            <div class="p-6">
                <form action="{{ route('admin.archive.store') }}" method="POST" class="space-y-4">
                    @csrf

                    <div>
                        <label for="selected_table" class="block text-sm font-medium text-gray-700">Selected Table</label>
                        <input type="text" id="selected_table" x-model="selectedTableLabel" readonly class="mt-1 block w-full rounded-md border-gray-300 bg-gray-50 shadow-sm">
                        <input type="hidden" name="table" :value="table">
                        @error('table')
                            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="selected_record" class="block text-sm font-medium text-gray-700">Selected Record</label>
                        <input type="text" id="selected_record" x-model="selectedLabel" readonly class="mt-1 block w-full rounded-md border-gray-300 bg-gray-50 shadow-sm">
                        <input type="hidden" name="record_id" :value="selectedId">
                        @error('record_id')
                            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="reason" class="block text-sm font-medium text-gray-700">Reason</label>
                        <textarea name="reason" id="reason" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Why is this record being archived?">{{ old('reason') }}</textarea>
                        @error('reason')
                            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="w-full rounded-md bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700" :disabled="!selectedId || !table">
                        Archive Record
                    </button>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function archiveSearch(searchUrl) {
            return {
                table: '{{ old('table') }}',
                search: '',
                results: [],
                selectedId: '{{ old('record_id') }}',
                selectedLabel: '',
                message: '',

                get selectedTableLabel() {
                    if (!this.table) {
                        return '';
                    }

                    return this.table.charAt(0).toUpperCase() + this.table.slice(1);
                },

                resetResults() {
                    this.results = [];
                    this.selectedId = '';
                    this.selectedLabel = '';
                    this.message = '';
                },

                selectResult(result) {
                    this.selectedId = result.id;
                    this.selectedLabel = result.label;
                    this.message = `Selected ${result.label}.`;
                },

                async runSearch() {
                    if (!this.table) {
                        this.message = 'Select a table before searching.';
                        return;
                    }

                    if (!this.search || this.search.length < 2) {
                        this.message = 'Enter at least 2 characters to search.';
                        return;
                    }

                    this.message = 'Searching...';

                    const params = new URLSearchParams({
                        table: this.table,
                        search: this.search,
                    });

                    const response = await fetch(`${searchUrl}?${params.toString()}`, {
                        headers: {
                            'Accept': 'application/json',
                        },
                    });

                    if (!response.ok) {
                        this.message = 'Search failed. Please try again.';
                        return;
                    }

                    this.results = await response.json();
                    this.message = this.results.length > 0
                        ? `Found ${this.results.length} record(s).`
                        : 'No matching records found.';
                },
            };
        }
    </script>
@endpush
