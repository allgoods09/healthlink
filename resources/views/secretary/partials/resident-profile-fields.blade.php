@php
    $labels = ['occupation' => 'Occupation / Profession', 'employment_status' => 'Employment Status',
        'highest_education_level' => 'Highest Educational Attainment', 'education_status' => 'Education Status',
        'is_pwd' => 'PWD', 'disability_type' => 'Disability Type', 'is_ofw' => 'OFW',
        'is_solo_parent' => 'Solo Parent', 'is_osy' => 'Out-of-School Youth',
        'is_osc' => 'Out-of-School Child', 'is_ip' => 'Indigenous Person', 'ethnicity' => 'Ethnicity'];
@endphp
@foreach(\App\Support\ResidentProfileData::FIELDS as $field)
    @if(!isset($changedOnly) || array_key_exists($field, $changedOnly))
        @php
            $key = $profilePrefix ? $profilePrefix.'.'.$field : $field;
            $name = $profileIndex !== null ? 'residents['.$profileIndex.']['.$field.']' : $field;
            $id = 'profile_'.($profileIndex ?? 'correction').'_'.$field;
            $value = old($key, data_get($profileValues, $field));
            $options = \App\Support\ResidentProfileData::CHOICES[$field] ?? null;
            $flag = in_array($field, \App\Support\ResidentProfileData::FLAGS, true);
        @endphp
        <div>
            <label for="{{ $id }}" class="block text-sm font-medium text-slate-700">{{ $labels[$field] }}</label>
            @if($options || $flag)
                <select id="{{ $id }}" name="{{ $name }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                    @if($value === null)
                        <option value="" selected>Not recorded</option>
                    @endif
                    @foreach($options ?? ['0' => 'No', '1' => 'Yes'] as $optionKey => $option)
                        @php($optionValue = $flag ? $optionKey : $option)
                        <option value="{{ $optionValue }}" @selected($value !== null && (string) $value === (string) $optionValue)>{{ $option }}</option>
                    @endforeach
                </select>
            @else
                <input id="{{ $id }}" name="{{ $name }}" value="{{ $value }}" type="text" maxlength="{{ $field === 'ethnicity' ? 100 : 150 }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
            @endif
            <x-input-error :messages="$errors->get($key)" class="mt-2" />
        </div>
    @endif
@endforeach
