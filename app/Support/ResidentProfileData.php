<?php

namespace App\Support;

use App\Models\Resident;
use Illuminate\Support\Arr;

final class ResidentProfileData
{
    public const CHOICES = [
        'employment_status' => ['Employed', 'Unemployed', 'N/A'],
        'highest_education_level' => ['None', 'Elementary', 'High School', 'College', 'Post Grad', 'Vocational'],
        'education_status' => ['Graduate', 'Undergraduate', 'N/A'],
    ];

    public const FLAGS = ['is_pwd', 'is_ofw', 'is_solo_parent', 'is_osy', 'is_osc', 'is_ip'];

    public const FIELDS = ['occupation', 'employment_status', 'highest_education_level', 'education_status',
        'is_pwd', 'disability_type', 'is_ofw', 'is_solo_parent', 'is_osy', 'is_osc', 'is_ip', 'ethnicity'];

    public static function rules(string $prefix = ''): array
    {
        $rules = [];
        foreach (['occupation' => 150, 'disability_type' => 150, 'ethnicity' => 100] as $field => $max) {
            $rules[$prefix.$field] = ['sometimes', 'nullable', 'string', 'max:'.$max];
        }
        foreach (self::CHOICES as $field => $choices) {
            $rules[$prefix.$field] = $prefix === 'proposed_changes.' ? ['sometimes', 'required', 'in:'.implode(',', $choices)]
                : ['sometimes', 'nullable', 'in:'.implode(',', $choices)];
        }
        foreach (self::FLAGS as $field) {
            $rules[$prefix.$field] = $prefix === 'proposed_changes.' ? ['sometimes', 'required', 'boolean'] : ['sometimes', 'nullable', 'boolean'];
        }
        return $rules;
    }

    public static function snapshot(Resident $resident): array
    {
        $profile = $resident->socioEconomicProfile;
        return collect(self::FIELDS)->mapWithKeys(fn ($field) => [$field => $profile?->{$field}])->all();
    }

    public static function persist(Resident $resident, array $values, bool $create = false): void
    {
        $data = Arr::only($values, self::FIELDS);
        // The authoritative enum/flag columns are NOT NULL. Blank selections do
        // not clear these columns or invent values for an absent profile.
        foreach ([...array_keys(self::CHOICES), ...self::FLAGS] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === null) unset($data[$field]);
        }
        if (array_key_exists('is_pwd', $data) && ! $data['is_pwd']) $data['disability_type'] = null;
        if ($create || $data) $resident->socioEconomicProfile()->updateOrCreate(['resident_id' => $resident->id], $data);
    }
}
