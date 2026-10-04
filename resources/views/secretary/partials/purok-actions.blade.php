<div class="flex flex-wrap items-center gap-2">
    <x-record-action :href="route('secretary.puroks.index')" variant="back">Back</x-record-action>
    <x-record-action :href="route('secretary.puroks.edit', $purok)" variant="edit">Edit Purok</x-record-action>
    @if(!$purok->trashed())
        <form action="{{ route('secretary.puroks.toggle-status', $purok) }}" method="POST" class="inline">
            @csrf
            @method('PATCH')
            <x-record-action type="submit" :variant="$purok->is_active ? 'deactivate' : 'activate'">{{ $purok->is_active ? 'Deactivate' : 'Activate' }}</x-record-action>
        </form>
    @endif
</div>
