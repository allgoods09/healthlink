<?php

namespace App\Http\Requests\Bns;

use App\Models\InfantFeedingLog;
use App\Models\Resident;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class StoreInfantFeedingLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'bns';
    }

    public function rules(): array
    {
        return [
            'resident_id' => ['required', 'integer', 'exists:residents,id'],
            'observed_on' => ['required', 'date', 'before_or_equal:today'],
            'feeding_method' => ['required', 'string', 'in:' . implode(',', array_keys(InfantFeedingLog::METHODS))],
            'notes' => ['nullable', 'string', 'max:1500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            /** @var Resident|null $mother */
            $mother = $this->route('resident');
            $infant = Resident::query()->with('household.purok')->find($this->integer('resident_id'));

            if (! $mother || ! $infant || ! $this->filled('observed_on')) {
                return;
            }

            if ((int) $infant->household?->purok?->barangay_id !== (int) $this->user()->assigned_barangay_id) {
                $validator->errors()->add('resident_id', 'Select a verified child from your assigned barangay.');
                return;
            }

            if (! $infant->birth_date) {
                $validator->errors()->add('resident_id', 'This child is missing a birth date and cannot be assessed for feeding history yet.');
                return;
            }

            $observedOn = Carbon::parse($this->input('observed_on'))->startOfDay();
            $ageInMonths = $infant->birth_date->copy()->startOfDay()->diffInMonths($observedOn, false);

            if ($ageInMonths < 0 || $ageInMonths > 24) {
                $validator->errors()->add('resident_id', 'Infant feeding follow-up is currently limited to verified children aged 0 to 24 months on the observation date.');
            }
        });
    }
}
