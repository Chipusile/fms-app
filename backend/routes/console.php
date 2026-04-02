<?php

use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Maintenance\ReminderDispatchService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('platform:bootstrap {email} {name=Platform Administrator} {--password=} {--force-password-reset}', function () {
    $password = $this->option('password') ?: Str::password(24);

    Artisan::call('db:seed', [
        '--class' => PermissionSeeder::class,
        '--force' => true,
    ]);

    $user = User::withoutGlobalScopes()->updateOrCreate(
        ['email' => $this->argument('email')],
        [
            'name' => $this->argument('name'),
            'password' => Hash::make($password),
            'is_super_admin' => true,
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
            'tenant_id' => null,
        ],
    );

    $this->newLine();
    $this->info('Platform bootstrap complete.');
    $this->line('Permissions seeded and super admin account is ready.');
    $this->line(sprintf('Email: %s', $user->email));

    if (! $this->option('password')) {
        $this->warn(sprintf('Generated password (store securely now): %s', $password));
    }

    if ($this->option('force-password-reset')) {
        $this->comment('Password reset enforcement is not automated yet. Record this as an operational follow-up.');
    }
})->purpose('Seed global permissions and create or update the initial super admin account for production.');

Artisan::command('fleet:dispatch-reminders {--tenant=}', function () {
    $tenantId = $this->option('tenant') ? (int) $this->option('tenant') : null;
    $totals = app(ReminderDispatchService::class)->dispatch($tenantId);

    $this->info(sprintf(
        'Reminder dispatch complete. Maintenance: %d, Compliance: %d, Components: %d',
        $totals['maintenance_due'],
        $totals['compliance_expiring'],
        $totals['component_due_replacement'],
    ));
})->purpose('Dispatch in-app reminders for maintenance, compliance, and component thresholds.');

Schedule::command('fleet:dispatch-reminders')
    ->hourly()
    ->withoutOverlapping();
