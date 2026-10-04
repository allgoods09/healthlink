<?php

namespace App\Support;

use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SecretaryRbiSelection
{
    public const DOCUMENTS = [
        'household_rbi' => 'RBI Form A - Household',
        'resident_rbi' => 'RBI Form B - Resident',
    ];

    public const COVERAGES = [
        'barangay' => 'Entire Barangay',
        'puroks' => 'Selected Purok(s)',
        'households' => 'Selected Household(s)',
    ];

    public const FIELDS = ['document_type', 'coverage', 'purok_ids', 'household_ids', 'record_status', 'social_aid', 'sex', 'resident_status', 'age_min', 'age_max'];

    public function normalize(array $input, int $barangayId, int $step = 4): array
    {
        $document = Validator::make($input, [
            'document_type' => [$step >= 2 ? 'required' : 'nullable', 'in:household_rbi,resident_rbi'],
        ], ['document_type.required' => 'Choose an RBI document before continuing.'])->validate();
        $selection = ['document_type' => $document['document_type'] ?? null];
        if ($step < 3) {
            return $selection;
        }

        $coverage = Validator::make($input, [
            'coverage' => ['required', 'in:barangay,puroks,households'],
        ], ['coverage.required' => 'Choose which area or households to include.'])->validate();
        $selection += $coverage;
        if ($coverage['coverage'] !== 'barangay') {
            $field = $coverage['coverage'] === 'puroks' ? 'purok_ids' : 'household_ids';
            $label = $field === 'purok_ids' ? 'purok' : 'household';
            $ids = Validator::make($input, [
                $field => ['required', 'array', 'min:1'],
                $field.'.*' => ['required', 'integer', 'min:1', 'distinct'],
            ], [$field.'.required' => "Select at least one $label.", $field.'.min' => "Select at least one $label."])->validate();
            $selected = array_map('intval', array_values($ids[$field]));
            $allowed = ($field === 'purok_ids'
                ? Purok::query()->where('barangay_id', $barangayId)
                : $this->households($barangayId))
                ->whereKey($selected)->pluck('id')->all();
            if (count($allowed) !== count($selected)) {
                throw ValidationException::withMessages([$field => "Every selected $label must exist in your assigned barangay."]);
            }
            $selection[$field] = $selected;
        }
        if ($step < 4) {
            return $selection;
        }

        $rules = ['record_status' => ['nullable', 'in:all,active,inactive']];
        if ($selection['document_type'] === 'household_rbi') {
            $rules['social_aid'] = ['nullable', 'in:all,yes,no'];
        } else {
            $rules += [
                'sex' => ['nullable', 'in:Male,Female'],
                'resident_status' => ['nullable', 'in:active,deceased,relocated'],
                'age_min' => ['nullable', 'integer', 'min:0', 'max:150'],
                'age_max' => ['nullable', 'integer', 'min:0', 'max:150'],
            ];
        }
        $validator = Validator::make($input, $rules);
        $validator->after(function ($validator) use ($input, $selection): void {
            if ($selection['document_type'] === 'resident_rbi'
                && ! $validator->errors()->has('age_min') && ! $validator->errors()->has('age_max')
                && isset($input['age_min'], $input['age_max']) && $input['age_min'] !== '' && $input['age_max'] !== ''
                && (int) $input['age_min'] > (int) $input['age_max']) {
                $validator->errors()->add('age_max', 'Maximum age must be equal to or greater than minimum age.');
            }
        });
        $filters = $validator->validate();
        $filters['record_status'] = $filters['record_status'] ?? 'active';
        if ($selection['document_type'] === 'household_rbi') {
            $filters['social_aid'] = $filters['social_aid'] ?? 'all';
        }

        return $selection + array_filter($filters, fn ($value) => $value !== null && $value !== '');
    }

    public function households(int $barangayId): Builder
    {
        return Household::query()->whereHas('purok', fn (Builder $query) => $query->where('barangay_id', $barangayId));
    }

    public function query(array $selection, int $barangayId): Builder
    {
        $isHousehold = $selection['document_type'] === 'household_rbi';
        $query = $isHousehold
            ? $this->households($barangayId)->orderBy('purok_id')->orderBy('household_no')
            : Resident::query()->whereHas('household.purok', fn (Builder $query) => $query->where('barangay_id', $barangayId))
                ->orderBy('last_name')->orderBy('first_name');

        if ($selection['coverage'] === 'puroks') {
            if ($isHousehold) {
                $query->whereIn('purok_id', $selection['purok_ids']);
            } else {
                $query->whereHas('household', fn (Builder $query) => $query->whereIn('purok_id', $selection['purok_ids']));
            }
        } elseif ($selection['coverage'] === 'households') {
            $query->whereIn($isHousehold ? 'id' : 'household_id', $selection['household_ids']);
        }
        if ($selection['record_status'] !== 'all') {
            $query->where('is_active', $selection['record_status'] === 'active');
        }
        if ($isHousehold) {
            if ($selection['social_aid'] !== 'all') {
                $query->where('is_social_aid_beneficiary', $selection['social_aid'] === 'yes');
            }
        } else {
            foreach (['sex', 'resident_status'] as $field) {
                if (isset($selection[$field])) {
                    $query->where($field, $selection[$field]);
                }
            }
            if (isset($selection['age_min'])) {
                $query->whereDate('birth_date', '<=', now()->subYears((int) $selection['age_min'])->endOfDay());
            }
            if (isset($selection['age_max'])) {
                $query->whereDate('birth_date', '>=', now()->subYears((int) $selection['age_max'] + 1)->addDay()->startOfDay());
            }
        }

        return $query;
    }

    public function filterLabels(array $selection): array
    {
        $labels = [
            ($selection['document_type'] === 'household_rbi' ? 'Household' : 'Resident').' Record Status' => ucfirst($selection['record_status']).($selection['record_status'] === 'all' ? ' records' : ' only'),
        ];
        if ($selection['document_type'] === 'household_rbi') {
            $labels['Social Aid Beneficiary'] = match ($selection['social_aid']) {
                'yes' => 'Beneficiary only', 'no' => 'Non-beneficiary only', default => 'All households',
            };
        } else {
            $labels += ['Sex' => $selection['sex'] ?? 'All', 'Resident Lifecycle Status' => ucfirst($selection['resident_status'] ?? 'all')];
            foreach (['age_min' => 'Minimum Age', 'age_max' => 'Maximum Age'] as $key => $label) {
                if (isset($selection[$key])) {
                    $labels[$label] = $selection[$key].' years';
                }
            }
        }

        return $labels;
    }
}
