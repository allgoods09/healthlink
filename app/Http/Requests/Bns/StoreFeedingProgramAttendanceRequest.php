<?php

namespace App\Http\Requests\Bns;

use App\Models\FeedingProgramAttendance;
use App\Models\FeedingProgramEnrollment;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class StoreFeedingProgramAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'bns';
    }

    public function rules(): array
    {
        return [
            'attendance_date' => ['required', 'date', 'before_or_equal:today'],
            'attendance_status' => ['required', 'string', 'in:' . implode(',', array_keys(FeedingProgramAttendance::STATUSES))],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            /** @var FeedingProgramEnrollment|null $enrollment */
            $enrollment = $this->route('enrollment');

            if (! $enrollment || ! $this->filled('attendance_date')) {
                return;
            }

            $attendanceDate = Carbon::parse($this->input('attendance_date'))->startOfDay();

            if ($enrollment->feedingProgram?->starts_on && $attendanceDate->lt($enrollment->feedingProgram->starts_on->copy()->startOfDay())) {
                $validator->errors()->add('attendance_date', 'Attendance date cannot be earlier than the feeding program start date.');
            }

            if ($enrollment->feedingProgram?->ends_on && $attendanceDate->gt($enrollment->feedingProgram->ends_on->copy()->endOfDay())) {
                $validator->errors()->add('attendance_date', 'Attendance date cannot be later than the feeding program end date.');
            }

            $duplicate = FeedingProgramAttendance::query()
                ->where('enrollment_id', $enrollment->id)
                ->whereDate('attendance_date', $attendanceDate)
                ->exists();

            if ($duplicate) {
                $validator->errors()->add('attendance_date', 'Attendance was already recorded for this child on the selected date.');
            }
        });
    }
}
