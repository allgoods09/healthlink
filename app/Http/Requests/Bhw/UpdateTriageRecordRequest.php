<?php

namespace App\Http\Requests\Bhw;

class UpdateTriageRecordRequest extends StoreTriageRecordRequest
{
    public function rules(): array
    {
        return array_diff_key(parent::rules(), ['resident_id' => true]);
    }
}
