<div class="flex flex-wrap items-center gap-2">
    @if(\Illuminate\Support\Facades\Route::has('secretary.households.pdf'))
        <x-record-action :href="route('secretary.households.pdf', $household)" variant="document">Download RBI PDF</x-record-action>
    @endif
    @if(\Illuminate\Support\Facades\Route::has('secretary.households.print'))
        <x-record-action :href="route('secretary.households.print', $household)" variant="document" target="_blank" rel="noopener">Open Print View</x-record-action>
    @endif
    <x-record-action :href="route('secretary.residents.create', ['household_id' => $household->id, 'purok_id' => $household->purok_id, 'barangay_id' => $household->purok->barangay_id])" variant="add">Add Resident</x-record-action>
    <x-record-action :href="route('secretary.households.edit', $household)" variant="edit">Edit</x-record-action>
    @if(!$household->trashed())
        <form action="{{ route('secretary.households.toggle-status', $household) }}" method="POST" class="inline">
            @csrf
            @method('PATCH')
            <x-record-action type="submit" :variant="$household->is_active ? 'deactivate' : 'activate'">{{ $household->is_active ? 'Deactivate' : 'Activate' }}</x-record-action>
        </form>
    @endif
    <x-record-action :href="route('secretary.households.index')" variant="back">Back</x-record-action>
</div>
