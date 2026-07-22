<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('healthlink:create-admin {email} {--name=} {--password=}', function () {
    $email = strtolower(trim((string) $this->argument('email')));
    $name = trim((string) ($this->option('name') ?: 'HealthLink Admin'));
    $password = (string) $this->option('password');

    if ($password === '') {
        $password = (string) $this->secret('Enter the password for this admin account');
    }

    if ($password === '') {
        $this->error('Password is required.');

        return self::FAILURE;
    }

    $existingUser = User::withTrashed()->where('email', $email)->first();

    if ($existingUser && $existingUser->trashed()) {
        $existingUser->restore();
    }

    $user = User::updateOrCreate(
        ['email' => $email],
        [
            'name' => $name,
            'password' => Hash::make($password),
            'role' => 'admin',
            'approval_status' => User::APPROVAL_APPROVED,
            'registered_via' => 'admin',
            'requested_role' => 'admin',
            'assigned_barangay_id' => null,
            'assigned_purok_id' => null,
            'requested_barangay_id' => null,
            'requested_purok_id' => null,
            'approval_notes' => 'Provisioned via artisan command.',
            'approved_at' => now(),
            'approved_by' => null,
            'rejected_at' => null,
            'rejected_by' => null,
            'email_verified_at' => now(),
            'is_active' => true,
        ]
    );

    $this->newLine();
    $this->info('Admin account is ready.');
    $this->line("Email: {$user->email}");
    $this->line("Name: {$user->name}");
    $this->line("Role: {$user->role}");
    $this->line('Email verified: yes');
    $this->line('Approval status: approved');

    return self::SUCCESS;
})->purpose('Create or update a verified HealthLink admin account');
