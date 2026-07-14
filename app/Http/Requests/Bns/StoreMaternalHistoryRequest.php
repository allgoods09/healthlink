<?php

namespace App\Http\Requests\Bns;

use App\Models\MaternalNutritionHistory;
use App\Models\Resident;
use Illuminate\Foundation\Http\FormRequest;

class StoreMaternalHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'bns';
    }

    public function rules(): array
    {
        return [
            'event_type' => ['required', 'string', 'in:' . implode(',', array_keys(MaternalNutritionHistory::EVENT_TYPES))],
            'event_date' => ['required', 'date', 'before_or_equal:today'],
            'gestational_age_weeks' => ['nullable', 'integer', 'min:1', 'max:45'],
            'weight_kg' => ['nullable', 'numeric', 'min:0.5', 'max:250'],
            'notes' => ['nullable', 'string', 'max:1500'],
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

            if ($resident->sex !== 'Female') {
                $validator->errors()->add('event_type', 'Maternal history is limited to verified female residents.');
            }
        });
    }
}
