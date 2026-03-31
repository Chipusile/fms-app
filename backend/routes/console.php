<?php

use App\Services\Maintenance\ReminderDispatchService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

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
