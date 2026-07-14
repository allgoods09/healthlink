<?php

namespace App\Http\Requests\Secretary;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ReviewDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()?->role === 'secretary';
    }

    public function rules(): array
    {
        return [
            'review_notes' => ['required', 'string', 'max:2000'],
        ];
    }
}
