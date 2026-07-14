<?php

namespace App\Http\Requests\Bns;

use App\Models\FeedingProgramEnrollment;
use App\Models\FeedingProgramProgressLog;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class StoreFeedingProgramProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'bns';
    }

    public function rules(): array
    {
        return [
            'logged_on' => ['required', 'date', 'before_or_equal:today'],
            'week_number' => ['nullable', 'integer', 'min:1', 'max:104'],
            'weight_kg' => ['nullable', 'numeric', 'min:0.5', 'max:60'],
            'remarks' => ['nullable', 'string', 'max:1500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            /** @var FeedingProgramEnrollment|null $enrollment */
            $enrollment = $this->route('enrollment');

            if (! $enrollment || ! $this->filled('logged_on')) {
                return;
            }

            $loggedOn = Carbon::parse($this->input('logged_on'))->startOfDay();

            if ($enrollment->feedingProgram?->starts_on && $loggedOn->lt($enrollment->feedingProgram->starts_on->copy()->startOfDay())) {
                $validator->errors()->add('logged_on', 'Progress date cannot be earlier than the feeding program start date.');
            }

            if ($enrollment->feedingProgram?->ends_on && $loggedOn->gt($enrollment->feedingProgram->ends_on->copy()->endOfDay())) {
                $validator->errors()->add('logged_on', 'Progress date cannot be later than the feeding program end date.');
            }

            $duplicate = FeedingProgramProgressLog::query()
                ->where('enrollment_id', $enrollment->id)
                ->whereDate('logged_on', $loggedOn)
                ->exists();

            if ($duplicate) {
                $validator->errors()->add('logged_on', 'A progress entry for this child already exists on the selected date.');
            }
        });
    }
}
