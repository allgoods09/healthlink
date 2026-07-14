<?php

namespace App\Http\Requests\Bhw;

use App\Models\CommunityCampaignAssignment;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCommunityCampaignAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'bhw';
    }

    public function rules(): array
    {
        return [
            'assignment_status' => ['required', 'string', 'in:' . implode(',', array_keys(CommunityCampaignAssignment::STATUSES))],
            'field_notes' => ['nullable', 'string', 'max:1500'],
        ];
    }
}
