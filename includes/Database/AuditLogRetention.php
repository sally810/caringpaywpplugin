<?php

namespace CaringPays\CareAdvisor\Database;

final class AuditLogRetention
{
    public const CRON_HOOK = 'cp_care_advisor_purge_old_audit_logs';

    public static function scheduleDailyCleanup(): void
    {
        if (wp_next_scheduled(self::CRON_HOOK)) {
            return;
        }

        wp_schedule_event(time(), 'daily', self::CRON_HOOK);
    }

    public static function unscheduleDailyCleanup(): void
    {
        $scheduledTimestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($scheduledTimestamp === false) {
            return;
        }

        wp_unschedule_event($scheduledTimestamp, self::CRON_HOOK);
    }

    public static function purgeOlderThanSevenYears(): void
    {
        global $wpdb;

        if (! isset($wpdb)) {
            return;
        }

        $table = $wpdb->prefix . 'cp_audit_logs';
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d YEAR)",
                7
            )
        );
    }
}
