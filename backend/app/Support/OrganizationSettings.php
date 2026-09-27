<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Arr;

/** Defaults and validation rules for per-tenant settings stored in organizations.settings. */
final class OrganizationSettings
{
    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'date_format' => 'DD.MM.YYYY',
            'time_format' => 'HH:mm',
            // ISO day numbers: 1 = Monday ... 7 = Sunday
            'work_week' => [1, 2, 3, 4, 5],
            'attendance' => [
                'default_grace_minutes' => 10,
                // Punches closer together than this are treated as one (double scans).
                'duplicate_punch_window_seconds' => 60,
                // Mark missing employees absent when closing the day.
                'auto_mark_absent' => true,
                'monitor_refresh_seconds' => 30,
            ],
            'payroll' => [
                // fixed: full monthly salary, absences handled by penalty rules.
                // prorated: salary × worked_days / scheduled_days.
                'monthly_mode' => 'fixed',
                'overtime_pay_enabled' => false,
                'overtime_rate_multiplier' => 1.5,
                'count_incomplete_as_worked' => true,
            ],
            'penalties' => [
                'auto_approve' => false,
            ],
            'notifications' => [
                'employee_late' => ['database'],
                'employee_absent' => ['database'],
                'early_leave' => ['database'],
                'payroll_calculated' => ['database', 'mail'],
                'lead_assigned' => ['database', 'mail'],
                'deal_stage_changed' => ['database'],
                'task_due' => ['database', 'mail'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $stored
     * @return array<string, mixed>
     */
    public static function merge(?array $stored): array
    {
        return array_replace_recursive(self::defaults(), $stored ?? []);
    }

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        $channels = ['array'];

        return [
            'settings' => ['sometimes', 'array'],
            'settings.date_format' => ['sometimes', 'string', 'in:DD.MM.YYYY,YYYY-MM-DD,MM/DD/YYYY,DD/MM/YYYY'],
            'settings.time_format' => ['sometimes', 'string', 'in:HH:mm,hh:mm A'],
            'settings.work_week' => ['sometimes', 'array', 'min:1', 'max:7'],
            'settings.work_week.*' => ['integer', 'between:1,7', 'distinct'],
            'settings.attendance.default_grace_minutes' => ['sometimes', 'integer', 'between:0,240'],
            'settings.attendance.duplicate_punch_window_seconds' => ['sometimes', 'integer', 'between:0,3600'],
            'settings.attendance.auto_mark_absent' => ['sometimes', 'boolean'],
            'settings.attendance.monitor_refresh_seconds' => ['sometimes', 'integer', 'between:10,600'],
            'settings.payroll.monthly_mode' => ['sometimes', 'in:fixed,prorated'],
            'settings.payroll.overtime_pay_enabled' => ['sometimes', 'boolean'],
            'settings.payroll.overtime_rate_multiplier' => ['sometimes', 'numeric', 'between:1,5'],
            'settings.payroll.count_incomplete_as_worked' => ['sometimes', 'boolean'],
            'settings.penalties.auto_approve' => ['sometimes', 'boolean'],
            'settings.notifications' => ['sometimes', 'array'],
            'settings.notifications.*' => $channels,
            'settings.notifications.*.*' => ['string', 'in:database,mail'],
        ];
    }

    /**
     * Keep only known keys so arbitrary client data never lands in settings.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function sanitize(array $input): array
    {
        $defaults = Arr::dot(self::defaults());
        $clean = [];

        foreach (Arr::dot($input) as $key => $value) {
            foreach (array_keys($defaults) as $known) {
                if ($key === $known || str_starts_with($key, $known.'.')) {
                    Arr::set($clean, $key, $value);
                    break;
                }
            }
        }

        // List-valued settings are replaced wholesale, not merged index by index.
        foreach (['work_week'] as $listKey) {
            if (array_key_exists($listKey, $input)) {
                $clean[$listKey] = array_values(array_map('intval', (array) $input[$listKey]));
            }
        }
        foreach (array_keys(self::defaults()['notifications']) as $event) {
            if (isset($input['notifications'][$event])) {
                $clean['notifications'][$event] = array_values((array) $input['notifications'][$event]);
            }
        }

        return $clean;
    }
}
