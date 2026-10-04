<div class="flex flex-wrap items-center gap-2">
    @if(\Illuminate\Support\Facades\Route::has('secretary.residents.pdf'))
        <x-record-action :href="route('secretary.residents.pdf', $resident)" variant="document">Download RBI PDF</x-record-action>
    @endif
    @if(\Illuminate\Support\Facades\Route::has('secretary.residents.print'))
        <x-record-action :href="route('secretary.residents.print', $resident)" variant="document" target="_blank" rel="noopener">Open Print View</x-record-action>
    @endif
    <x-record-action :href="route('secretary.residents.edit', $resident)" variant="edit">Edit</x-record-action>
    @if($canRelocate ?? false)
        <x-record-action :href="route('secretary.residents.relocate.edit', $resident)" variant="relocate">Relocate</x-record-action>
    @endif
    @if(!$resident->trashed())
        <form action="{{ route('secretary.residents.toggle-status', $resident) }}" method="POST" class="inline">
            @csrf
            @method('PATCH')
            <x-record-action type="submit" :variant="$resident->is_active ? 'deactivate' : 'activate'">{{ $resident->is_active ? 'Deactivate' : 'Activate' }}</x-record-action>
        </form>
    @endif
    <x-record-action :href="route('secretary.households.show', $resident->household)" variant="view">View Household</x-record-action>
    <x-record-action :href="route('secretary.residents.index')" variant="back">Back</x-record-action>
</div>
