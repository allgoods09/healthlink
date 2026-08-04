@props([
    'action',
    'method' => 'POST',
    'title',
    'description',
    'triggerLabel',
    'triggerClass' => 'inline-flex items-center rounded-md bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700',
    'submitLabel' => 'Continue',
    'confirmationWord' => 'CONFIRM',
    'reasonName' => 'action_reason',
    'reasonLabel' => 'Reason for this action',
    'reasonPlaceholder' => 'Explain why this action is necessary.',
])

<form
    method="POST"
    action="{{ $action }}"
    class="inline"
    data-confirm
    data-confirm-label="{{ $submitLabel }}"
    data-confirm-word="{{ $confirmationWord }}"
    data-confirm-reason-required="true"
    data-confirm-reason-name="{{ $reasonName }}"
    data-confirm-reason-label="{{ $reasonLabel }}"
    data-confirm-reason-placeholder="{{ $reasonPlaceholder }}"
>
    @csrf
    @if(! in_array(strtoupper($method), ['GET', 'POST'], true))
        @method($method)
    @endif

    {{ $slot }}

    <button type="submit" class="{{ $triggerClass }}">
        {{ $triggerLabel }}
    </button>
</form>
