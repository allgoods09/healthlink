<?php

namespace App\Providers;

use App\Models\ArchivedRecord;
use App\Models\AuditLog;
use App\Models\Backup;
use App\Models\Barangay;
use App\Models\BarangayCertificate;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\Setting;
use App\Models\SyncLog;
use App\Models\User;
use App\Policies\ArchivedRecordPolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\BackupPolicy;
use App\Policies\BarangayPolicy;
use App\Policies\BarangayCertificatePolicy;
use App\Policies\HouseholdPolicy;
use App\Policies\PurokPolicy;
use App\Policies\ResidentPolicy;
use App\Policies\SettingPolicy;
use App\Policies\SyncLogPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
        Barangay::class => BarangayPolicy::class,
        Purok::class => PurokPolicy::class,
        Household::class => HouseholdPolicy::class,
        Resident::class => ResidentPolicy::class,
        BarangayCertificate::class => BarangayCertificatePolicy::class,
        AuditLog::class => AuditLogPolicy::class,
        SyncLog::class => SyncLogPolicy::class,
        Backup::class => BackupPolicy::class,
        ArchivedRecord::class => ArchivedRecordPolicy::class,
        Setting::class => SettingPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Implicitly grant "Super Admin" role all permissions
        Gate::before(function ($user, $ability) {
            if ($user->role === 'admin') {
                return true;
            }
        });
    }
}
