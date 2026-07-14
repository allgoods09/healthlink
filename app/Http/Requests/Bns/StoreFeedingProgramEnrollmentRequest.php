<?php

namespace App\Http\Requests\Bns;

use App\Models\FeedingProgram;
use App\Models\FeedingProgramEnrollment;
use App\Models\OptMeasurement;
use App\Models\Resident;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class StoreFeedingProgramEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'bns';
    }

    public function rules(): array
    {
        return [
            'resident_id' => ['required', 'integer', 'exists:residents,id'],
            'enrolled_on' => ['required', 'date', 'before_or_equal:today'],
            'baseline_weight_kg' => ['nullable', 'numeric', 'min:0.5', 'max:60'],
            'baseline_nutritional_status' => ['nullable', 'string', 'max:255'],
            'completion_notes' => ['nullable', 'string', 'max:1500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            /** @var FeedingProgram|null $feedingProgram */
            $feedingProgram = $this->route('feedingProgram');
            $resident = Resident::query()->with('household.purok')->find($this->integer('resident_id'));

            if (! $feedingProgram || (int) $feedingProgram->barangay_id !== (int) $this->user()->assigned_barangay_id) {
                $validator->errors()->add('resident_id', 'Select a valid feeding program first.');
            }

            if (! $resident || (int) $resident->household?->purok?->barangay_id !== (int) $this->user()->assigned_barangay_id) {
                $validator->errors()->add('resident_id', 'Select a verified resident from your assigned barangay.');
                return;
            }

            if (! $resident->birth_date) {
                $validator->errors()->add('resident_id', 'This resident is missing a birth date and cannot be enrolled yet.');
                return;
            }

            if ($resident->resident_status !== Resident::STATUS_ACTIVE || ! $resident->is_active) {
                $validator->errors()->add('resident_id', 'Only active verified residents can be enrolled in feeding programs.');
            }

            if (! $this->filled('enrolled_on')) {
                return;
            }

            $enrolledOn = Carbon::parse($this->input('enrolled_on'))->startOfDay();
            $ageInMonths = $resident->birth_date->copy()->startOfDay()->diffInMonths($enrolledOn, false);

            if ($ageInMonths < 0 || $ageInMonths > 71) {
                $validator->errors()->add('resident_id', 'Feeding programs currently support verified children aged 0 to 71 months only.');
            }

            $alreadyEnrolled = FeedingProgramEnrollment::query()
                ->where('feeding_program_id', $feedingProgram?->id)
                ->where('resident_id', $resident->id)
                ->exists();

            if ($alreadyEnrolled) {
                $validator->errors()->add('resident_id', 'This child is already enrolled in the selected feeding program.');
            }
        });
    }
}
