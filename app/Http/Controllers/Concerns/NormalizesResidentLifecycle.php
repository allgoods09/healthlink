<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Resident;

trait NormalizesResidentLifecycle
{
    protected function normalizeResidentLifecycle(array $data): array
    {
        $status = $data['resident_status'] ?? Resident::STATUS_ACTIVE;
        $data['resident_status'] = $status;

        if ($status === Resident::STATUS_DECEASED) {
            $data['is_active'] = false;
            $data['moved_out_at'] = null;
        } elseif ($status === Resident::STATUS_RELOCATED) {
            $data['is_active'] = false;
            $data['date_of_death'] = null;
        } else {
            $data['is_active'] = $data['is_active'] ?? true;
            $data['date_of_death'] = null;
            $data['moved_out_at'] = null;
        }

        if ($status !== Resident::STATUS_DECEASED) {
            $data['date_of_death'] = null;
        }

        if ($status !== Resident::STATUS_RELOCATED) {
            $data['moved_out_at'] = null;
        }

        return $data;
    }
}
