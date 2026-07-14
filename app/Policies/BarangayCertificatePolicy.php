<?php

namespace App\Policies;

use App\Models\BarangayCertificate;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BarangayCertificatePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'secretary']);
    }

    public function view(User $user, BarangayCertificate $certificate): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        return $user->role === 'secretary'
            && (int) $certificate->barangay_id === (int) $user->assigned_barangay_id;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'secretary']);
    }
}
