<?php

namespace CaringPays\CareAdvisor\Database;

final class SchemaMigrator
{
    private const OPTION_SCHEMA_VERSION = 'cp_care_advisor_db_schema_version';
    private const SCHEMA_VERSION = '1.0.0';

    public static function migrate(): void
    {
        global $wpdb;

        if (! isset($wpdb)) {
            return;
        }

        $installedVersion = (string) get_option(self::OPTION_SCHEMA_VERSION, '');
        if ($installedVersion === self::SCHEMA_VERSION) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charsetCollate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix;

        dbDelta(self::sessionsSql($prefix, $charsetCollate));
        dbDelta(self::turnsSql($prefix, $charsetCollate));
        dbDelta(self::leadsSql($prefix, $charsetCollate));
        dbDelta(self::sourceOfTruthSql($prefix, $charsetCollate));
        dbDelta(self::eligibilityMatrixSql($prefix, $charsetCollate));
        dbDelta(self::auditLogsSql($prefix, $charsetCollate));
        dbDelta(self::optimizationQueueSql($prefix, $charsetCollate));

        update_option(self::OPTION_SCHEMA_VERSION, self::SCHEMA_VERSION, false);
    }

    private static function sessionsSql(string $prefix, string $charsetCollate): string
    {
        return "CREATE TABLE {$prefix}cp_sessions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_uuid CHAR(36) NOT NULL,
            state VARCHAR(64) NOT NULL,
            status VARCHAR(32) NOT NULL,
            started_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY session_uuid (session_uuid),
            KEY state (state),
            KEY status (status)
        ) {$charsetCollate};";
    }

    private static function turnsSql(string $prefix, string $charsetCollate): string
    {
        return "CREATE TABLE {$prefix}cp_turns (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id BIGINT UNSIGNED NOT NULL,
            turn_index INT UNSIGNED NOT NULL,
            role VARCHAR(20) NOT NULL,
            content LONGTEXT NOT NULL,
            state VARCHAR(64) NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY session_id (session_id),
            KEY state (state),
            KEY session_turn (session_id,turn_index)
        ) {$charsetCollate};";
    }

    private static function leadsSql(string $prefix, string $charsetCollate): string
    {
        return "CREATE TABLE {$prefix}cp_leads (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id BIGINT UNSIGNED NOT NULL,
            email VARCHAR(255) NOT NULL,
            state VARCHAR(64) NOT NULL,
            status VARCHAR(32) NOT NULL,
            lead_payload LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY session_id (session_id),
            KEY email (email),
            KEY state (state),
            KEY status (status)
        ) {$charsetCollate};";
    }

    private static function sourceOfTruthSql(string $prefix, string $charsetCollate): string
    {
        return "CREATE TABLE {$prefix}cp_source_of_truth (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            entity_type VARCHAR(64) NOT NULL,
            entity_key VARCHAR(191) NOT NULL,
            state VARCHAR(64) NOT NULL,
            status VARCHAR(32) NOT NULL,
            payload LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY entity_type_key (entity_type,entity_key),
            KEY state (state),
            KEY status (status)
        ) {$charsetCollate};";
    }

    private static function eligibilityMatrixSql(string $prefix, string $charsetCollate): string
    {
        return "CREATE TABLE {$prefix}cp_eligibility_matrix (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            state VARCHAR(64) NOT NULL,
            eligibility_group VARCHAR(64) NOT NULL,
            status VARCHAR(32) NOT NULL,
            matrix_payload LONGTEXT NOT NULL,
            effective_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY state (state),
            KEY status (status),
            KEY eligibility_group (eligibility_group)
        ) {$charsetCollate};";
    }

    private static function auditLogsSql(string $prefix, string $charsetCollate): string
    {
        return "CREATE TABLE {$prefix}cp_audit_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id BIGINT UNSIGNED NULL,
            actor_type VARCHAR(64) NOT NULL,
            action VARCHAR(120) NOT NULL,
            status VARCHAR(32) NOT NULL,
            state VARCHAR(64) NULL,
            event_payload LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY session_id (session_id),
            KEY status (status),
            KEY state (state),
            KEY created_at (created_at)
        ) {$charsetCollate};";
    }

    private static function optimizationQueueSql(string $prefix, string $charsetCollate): string
    {
        return "CREATE TABLE {$prefix}cp_optimization_queue (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id BIGINT UNSIGNED NULL,
            state VARCHAR(64) NULL,
            status VARCHAR(32) NOT NULL,
            optimization_type VARCHAR(100) NOT NULL,
            payload LONGTEXT NOT NULL,
            attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            available_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY session_id (session_id),
            KEY state (state),
            KEY status (status),
            KEY available_at (available_at)
        ) {$charsetCollate};";
    }
}
