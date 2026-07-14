<?php

namespace App\Http\Requests\Secretary;

use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class RelocateResidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->role === 'secretary';
    }

    public function rules(): array
    {
        return [
            'target_purok_id' => [
                'required',
                'exists:puroks,id',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $user = Auth::user();
                    $purok = Purok::find($value);

                    if (! $purok || (int) $purok->barangay_id !== (int) $user->assigned_barangay_id) {
                        $fail('You can only relocate residents within your assigned barangay.');
                    }

                    if ($purok && ! $purok->is_active) {
                        $fail('Residents can only be relocated into active puroks.');
                    }
                },
            ],
            'destination' => ['required', Rule::in(['existing_household', 'new_household'])],
            'target_household_id' => ['nullable', 'exists:households,id'],
            'new_household_no' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('households', 'household_no')->where(function ($query) {
                    return $query->where('purok_id', $this->input('target_purok_id'));
                }),
            ],
            'new_household_address' => ['nullable', 'string'],
            'new_household_social_aid' => ['boolean'],
            'set_as_household_head' => ['boolean'],
            'relationship_to_head' => ['nullable', 'string', 'max:100'],
            'moved_in_at' => ['nullable', 'date'],
            'status_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            /** @var Resident|null $resident */
            $resident = $this->route('resident');

            if (! $resident) {
                return;
            }

            $resident->loadMissing('household.purok');

            if ($resident->resident_status === Resident::STATUS_DECEASED) {
                $validator->errors()->add('target_purok_id', 'Deceased residents cannot be relocated.');
                return;
            }

            if ($this->input('destination') === 'existing_household') {
                if (! $this->filled('target_household_id')) {
                    $validator->errors()->add('target_household_id', 'Please select the destination household.');
                    return;
                }

                $household = Household::with('purok')->find($this->input('target_household_id'));

                if (! $household) {
                    return;
                }

                if ((int) $household->purok_id !== (int) $this->input('target_purok_id')) {
                    $validator->errors()->add('target_household_id', 'The selected household does not belong to the chosen purok.');
                }

                if (! $household->is_active || $household->trashed()) {
                    $validator->errors()->add('target_household_id', 'Residents can only be moved into active households.');
                }

                if ((int) $household->id === (int) $resident->household_id) {
                    $validator->errors()->add('target_household_id', 'The resident is already assigned to that household.');
                }

                if ($this->boolean('set_as_household_head')
                    && $household->head_resident_id
                    && (int) $household->head_resident_id !== (int) $resident->id) {
                    $validator->errors()->add('set_as_household_head', 'The destination household already has a designated head.');
                }
            }

            if ($this->input('destination') === 'new_household') {
                if (! $this->filled('new_household_no')) {
                    $validator->errors()->add('new_household_no', 'Please provide the new household number.');
                }

                if (! $this->filled('new_household_address')) {
                    $validator->errors()->add('new_household_address', 'Please provide the new household address.');
                }
            }

            if (! $this->boolean('set_as_household_head') && ! $this->filled('relationship_to_head')) {
                $validator->errors()->add('relationship_to_head', 'Please specify the resident relationship to the target household head.');
            }
        });
    }
}
