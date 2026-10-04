<?php

namespace App\Http\Requests\Secretary;

use App\Models\BarangayCertificate;
use App\Models\Household;
use App\Models\Resident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class IssueBarangayCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->role === 'secretary';
    }

    public function rules(): array
    {
        return [
            'certificate_type' => [
                'required',
                Rule::in([
                    BarangayCertificate::TYPE_CLEARANCE,
                    BarangayCertificate::TYPE_INDIGENCY,
                ]),
            ],
            'recipient_type' => [
                'required',
                Rule::in([
                    BarangayCertificate::RECIPIENT_RESIDENT,
                    BarangayCertificate::RECIPIENT_HOUSEHOLD,
                ]),
            ],
            'resident_id' => ['exclude_unless:recipient_type,resident', 'required', 'integer', Rule::exists('residents', 'id')->whereNull('deleted_at')],
            'household_id' => ['exclude_unless:recipient_type,household', 'required', 'integer', Rule::exists('households', 'id')->whereNull('deleted_at')],
            'use_printed_name' => ['sometimes', 'boolean'],
            'issued_to_name' => ['exclude_unless:use_printed_name,1', 'required', 'string', 'max:255'],
            'purpose' => ['required', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'issued_at' => ['required', 'date_format:Y-m-d\TH:i,Y-m-d\TH:i:s'],
            'review_token' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $user = Auth::user();

            if ($this->input('recipient_type') === BarangayCertificate::RECIPIENT_RESIDENT) {
                if (! $this->filled('resident_id')) {
                    $validator->errors()->add('resident_id', 'Please select the resident receiving the certificate.');
                    return;
                }

                $resident = Resident::with('household.purok')->find($this->input('resident_id'));

                if (! $resident) {
                    $validator->errors()->add('resident_id', 'Select an available active resident in your assigned barangay.');
                    return;
                }

                if ((int) $resident->household?->purok?->barangay_id !== (int) $user->assigned_barangay_id) {
                    $validator->errors()->add('resident_id', 'You can only issue certificates for residents in your assigned barangay.');
                }

                if ($resident->trashed() || ! $resident->is_active || $resident->resident_status !== Resident::STATUS_ACTIVE) {
                    $validator->errors()->add('resident_id', 'Certificates may only be issued to active resident records.');
                }
            }

            if ($this->input('recipient_type') === BarangayCertificate::RECIPIENT_HOUSEHOLD) {
                if (! $this->filled('household_id')) {
                    $validator->errors()->add('household_id', 'Please select the household receiving the certificate.');
                    return;
                }

                $household = Household::with('purok')->find($this->input('household_id'));

                if (! $household) {
                    $validator->errors()->add('household_id', 'Select an available active household in your assigned barangay.');
                    return;
                }

                if ((int) $household->purok?->barangay_id !== (int) $user->assigned_barangay_id) {
                    $validator->errors()->add('household_id', 'You can only issue certificates for households in your assigned barangay.');
                }

                if ($household->trashed() || ! $household->is_active) {
                    $validator->errors()->add('household_id', 'Certificates may only be issued to active households.');
                }
            }
        });
    }
}
