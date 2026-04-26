<?php

namespace App\Enums;

use InvalidArgumentException;

enum SettingKey: string
{
    case TripApprovalRequired = 'approvals.trip_approval_required';
    case InspectionCriticalRequiresReview = 'approvals.inspection_critical_requires_review';
    case IncidentReviewSeverities = 'approvals.incident_review_severities';
    case MaintenanceReminderDays = 'maintenance.reminder_days';
    case MaintenanceReminderKmBuffer = 'maintenance.reminder_km_buffer';
    case ComplianceReminderDays = 'compliance.reminder_days';
    case ComponentReminderDays = 'component.reminder_days';
    case ComponentReminderKmBuffer = 'component.reminder_km_buffer';
    case NotificationsRemindersEnabled = 'notifications.reminders.enabled';
    case Timezone = 'timezone';
    case DateFormat = 'date_format';

    public function group(): string
    {
        return match ($this) {
            self::TripApprovalRequired,
            self::InspectionCriticalRequiresReview,
            self::IncidentReviewSeverities => 'approvals',
            self::MaintenanceReminderDays,
            self::MaintenanceReminderKmBuffer,
            self::ComponentReminderDays,
            self::ComponentReminderKmBuffer => 'maintenance',
            self::ComplianceReminderDays,
            self::NotificationsRemindersEnabled => 'notifications',
            self::Timezone,
            self::DateFormat => 'general',
        };
    }

    public function normalize(mixed $value): mixed
    {
        return match ($this) {
            self::TripApprovalRequired,
            self::InspectionCriticalRequiresReview,
            self::NotificationsRemindersEnabled => $this->normalizeBoolean($value),
            self::MaintenanceReminderDays,
            self::MaintenanceReminderKmBuffer,
            self::ComplianceReminderDays,
            self::ComponentReminderDays,
            self::ComponentReminderKmBuffer => $this->normalizePositiveInteger($value),
            self::IncidentReviewSeverities => $this->normalizeSeverityList($value),
            self::Timezone,
            self::DateFormat => $this->normalizeString($value),
        };
    }

    private function normalizeBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        throw new InvalidArgumentException('The value must be a boolean.');
    }

    private function normalizePositiveInteger(mixed $value): int
    {
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            $integer = (int) $value;

            if ($integer >= 0 && $integer <= 100000) {
                return $integer;
            }
        }

        throw new InvalidArgumentException('The value must be an integer between 0 and 100000.');
    }

    private function normalizeSeverityList(mixed $value): array
    {
        $allowed = ['low', 'medium', 'high', 'critical'];

        if (! is_array($value)) {
            throw new InvalidArgumentException('The value must be an array of severities.');
        }

        $severities = array_values(array_unique($value));

        if ($severities === [] || array_diff($severities, $allowed) !== []) {
            throw new InvalidArgumentException('The value contains an invalid severity.');
        }

        return $severities;
    }

    private function normalizeString(mixed $value): string
    {
        if (is_string($value) && strlen($value) <= 100) {
            return $value;
        }

        throw new InvalidArgumentException('The value must be a string of 100 characters or fewer.');
    }
}
