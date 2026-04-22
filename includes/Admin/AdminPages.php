<?php

namespace CaringPays\CareAdvisor\Admin;

use CaringPays\CareAdvisor\Escalation\EscalationCodes;
use CaringPays\CareAdvisor\Escalation\EscalationPolicy;

final class AdminPages
{
    private const CAPABILITY = 'manage_options';
    private const MENU_SLUG_DASHBOARD = 'caringpays-dashboard';
    private const MENU_SLUG_SOURCE_OF_TRUTH = 'caringpays-source-of-truth';
    private const MENU_SLUG_ESCALATIONS = 'caringpays-escalations';
    private const MENU_SLUG_CRM = 'caringpays-crm-integration';
    private const MENU_SLUG_SETTINGS = 'caringpays-settings';
    private const MENU_SLUG_RESET = 'caringpays-reset-tools';
    private const MENU_SLUG_OPT_IN = 'caringpays-opt-in';

    private const OPTION_OPT_IN_ENABLED = 'caringpays_operational_opt_in_enabled';
    private const OPTION_GHL_SETTINGS = 'caringpays_ghl_settings';
    private const OPTION_GHL_SECRET = 'caringpays_ghl_api_secret';
    private const OPTION_ESCALATION_RULES = 'caringpays_escalation_rules';
    private const OPTION_TEST_CONNECTION_STATUS = 'caringpays_ghl_test_connection_status';

    public static function boot(): void
    {
        add_action('admin_menu', [self::class, 'registerAdminMenus']);
        add_action('admin_init', [self::class, 'handleAdminActions']);

        add_filter(
            'plugin_action_links_' . plugin_basename(CARINGPAYS_CARE_ADVISOR_FILE),
            [self::class, 'pluginActionLinks']
        );
    }

    /**
     * @param array<int,string> $links
     * @return array<int,string>
     */
    public static function pluginActionLinks(array $links): array
    {
        $custom = [
            sprintf('<a href="%s">%s</a>', esc_url(self::adminPageUrl(self::MENU_SLUG_SOURCE_OF_TRUTH)), esc_html__('Edit Source of Truth', 'caringpays-care-advisor')),
            sprintf('<a href="%s">%s</a>', esc_url(self::adminPageUrl(self::MENU_SLUG_ESCALATIONS)), esc_html__('Escalations', 'caringpays-care-advisor')),
            sprintf('<a href="%s">%s</a>', esc_url(self::adminPageUrl(self::MENU_SLUG_OPT_IN)), esc_html__('Opt In', 'caringpays-care-advisor')),
            sprintf('<a href="%s">%s</a>', esc_url(self::adminPageUrl(self::MENU_SLUG_DASHBOARD)), esc_html__('Manage', 'caringpays-care-advisor')),
            sprintf('<a href="%s">%s</a>', esc_url(self::adminPageUrl(self::MENU_SLUG_RESET)), esc_html__('Reset', 'caringpays-care-advisor')),
        ];

        return array_merge($custom, $links);
    }

    public static function registerAdminMenus(): void
    {
        add_menu_page(
            __('CaringPays Dashboard', 'caringpays-care-advisor'),
            __('CaringPays', 'caringpays-care-advisor'),
            self::CAPABILITY,
            self::MENU_SLUG_DASHBOARD,
            [self::class, 'renderDashboardPage'],
            'dashicons-heart',
            56
        );

        add_submenu_page(self::MENU_SLUG_DASHBOARD, __('Dashboard', 'caringpays-care-advisor'), __('Dashboard', 'caringpays-care-advisor'), self::CAPABILITY, self::MENU_SLUG_DASHBOARD, [self::class, 'renderDashboardPage']);
        add_submenu_page(self::MENU_SLUG_DASHBOARD, __('Source of Truth', 'caringpays-care-advisor'), __('Source of Truth', 'caringpays-care-advisor'), self::CAPABILITY, self::MENU_SLUG_SOURCE_OF_TRUTH, [self::class, 'renderSourceOfTruthPage']);
        add_submenu_page(self::MENU_SLUG_DASHBOARD, __('Escalations', 'caringpays-care-advisor'), __('Escalations', 'caringpays-care-advisor'), self::CAPABILITY, self::MENU_SLUG_ESCALATIONS, [self::class, 'renderEscalationsPage']);
        add_submenu_page(self::MENU_SLUG_DASHBOARD, __('CRM Integration', 'caringpays-care-advisor'), __('CRM Integration', 'caringpays-care-advisor'), self::CAPABILITY, self::MENU_SLUG_CRM, [self::class, 'renderCrmIntegrationPage']);
        add_submenu_page(self::MENU_SLUG_DASHBOARD, __('Settings', 'caringpays-care-advisor'), __('Settings', 'caringpays-care-advisor'), self::CAPABILITY, self::MENU_SLUG_SETTINGS, [self::class, 'renderSettingsPage']);
        add_submenu_page(self::MENU_SLUG_DASHBOARD, __('Reset / Tools', 'caringpays-care-advisor'), __('Reset / Tools', 'caringpays-care-advisor'), self::CAPABILITY, self::MENU_SLUG_RESET, [self::class, 'renderResetToolsPage']);
        add_submenu_page(self::MENU_SLUG_DASHBOARD, __('Opt In', 'caringpays-care-advisor'), __('Opt In', 'caringpays-care-advisor'), self::CAPABILITY, self::MENU_SLUG_OPT_IN, [self::class, 'renderOptInPage']);
    }

    public static function handleAdminActions(): void
    {
        if (! is_admin() || ! current_user_can(self::CAPABILITY)) {
            return;
        }

        if (! isset($_POST['caringpays_admin_action'])) {
            return;
        }

        $action = sanitize_key((string) wp_unslash($_POST['caringpays_admin_action']));

        switch ($action) {
            case 'save_source_of_truth':
                self::saveSourceOfTruth();
                break;
            case 'save_escalations':
                self::saveEscalations();
                break;
            case 'save_ghl_settings':
                self::saveGhlSettings();
                break;
            case 'test_ghl_connection':
                self::testGhlConnection();
                break;
            case 'opt_in_enable':
                self::optInEnable();
                break;
            case 'run_reset':
                self::runResetWorkflow();
                break;
        }
    }

    public static function renderDashboardPage(): void
    {
        self::assertCapability();

        $activeTab = sanitize_key((string) ($_GET['tab'] ?? 'general'));
        $allowedTabs = ['general', 'source-of-truth', 'escalations', 'ghl-crm', 'consent', 'audit-logs'];
        if (! in_array($activeTab, $allowedTabs, true)) {
            $activeTab = 'general';
        }

        echo '<div class="wrap"><h1>' . esc_html__('CaringPays Dashboard', 'caringpays-care-advisor') . '</h1>';
        echo '<h2 class="nav-tab-wrapper">';
        foreach ($allowedTabs as $tab) {
            $url = add_query_arg(['page' => self::MENU_SLUG_DASHBOARD, 'tab' => $tab], admin_url('admin.php'));
            $class = $activeTab === $tab ? 'nav-tab nav-tab-active' : 'nav-tab';
            echo '<a class="' . esc_attr($class) . '" href="' . esc_url($url) . '">' . esc_html(ucwords(str_replace('-', ' ', $tab))) . '</a>';
        }
        echo '</h2>';

        echo '<p>' . esc_html__('Use this dashboard to manage plugin operations, source content, escalation safety controls, CRM sync, consent checks, and audit trail access.', 'caringpays-care-advisor') . '</p>';
        echo '<ul><li><a href="' . esc_url(self::adminPageUrl(self::MENU_SLUG_SOURCE_OF_TRUTH)) . '">' . esc_html__('Open Source of Truth', 'caringpays-care-advisor') . '</a></li>';
        echo '<li><a href="' . esc_url(self::adminPageUrl(self::MENU_SLUG_ESCALATIONS)) . '">' . esc_html__('Open Escalations', 'caringpays-care-advisor') . '</a></li>';
        echo '<li><a href="' . esc_url(self::adminPageUrl(self::MENU_SLUG_CRM)) . '">' . esc_html__('Open GHL CRM Integration', 'caringpays-care-advisor') . '</a></li></ul>';
        echo '</div>';
    }

    public static function renderSourceOfTruthPage(): void
    {
        self::assertCapability();

        global $wpdb;
        $table = $wpdb->prefix . 'cp_source_of_truth';
        $records = $wpdb->get_results("SELECT id, entity_type, entity_key, status, updated_at FROM {$table} ORDER BY updated_at DESC LIMIT 100", ARRAY_A);

        echo '<div class="wrap"><h1>' . esc_html__('Source of Truth', 'caringpays-care-advisor') . '</h1>';
        echo '<p>' . esc_html__('Add/edit/publish KB entries, approved response templates, restricted topics, and referral partner content. Only approved and published records should be consumed by chatbot workflows.', 'caringpays-care-advisor') . '</p>';
        echo '<form method="post">';
        wp_nonce_field('caringpays_save_source_of_truth', 'caringpays_source_nonce');
        echo '<input type="hidden" name="caringpays_admin_action" value="save_source_of_truth" />';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th><label for="entity_type">' . esc_html__('Type', 'caringpays-care-advisor') . '</label></th><td><select name="entity_type" id="entity_type">';
        $types = ['kb_entry' => 'KB Entry', 'response_template' => 'Response Template', 'restricted_topic' => 'Restricted Topic', 'referral_partner_content' => 'Referral Partner Content'];
        foreach ($types as $value => $label) {
            echo '<option value="' . esc_attr($value) . '">' . esc_html($label) . '</option>';
        }
        echo '</select></td></tr>';
        echo '<tr><th><label for="entity_key">' . esc_html__('Key', 'caringpays-care-advisor') . '</label></th><td><input type="text" class="regular-text" name="entity_key" id="entity_key" required /></td></tr>';
        echo '<tr><th><label for="status">' . esc_html__('Status', 'caringpays-care-advisor') . '</label></th><td><select name="status" id="status"><option value="draft">Draft</option><option value="approved">Approved</option><option value="published">Published</option></select></td></tr>';
        echo '<tr><th><label for="payload">' . esc_html__('Content', 'caringpays-care-advisor') . '</label></th><td><textarea class="large-text" rows="8" name="payload" id="payload" required></textarea></td></tr>';
        echo '</tbody></table>';
        submit_button(__('Save Source Item', 'caringpays-care-advisor'));
        echo '</form>';

        echo '<h2>' . esc_html__('Latest Entries', 'caringpays-care-advisor') . '</h2>';
        echo '<table class="widefat"><thead><tr><th>ID</th><th>Type</th><th>Key</th><th>Status</th><th>Updated</th></tr></thead><tbody>';
        if (empty($records)) {
            echo '<tr><td colspan="5">' . esc_html__('No records yet.', 'caringpays-care-advisor') . '</td></tr>';
        } else {
            foreach ($records as $record) {
                echo '<tr><td>' . esc_html((string) $record['id']) . '</td><td>' . esc_html((string) $record['entity_type']) . '</td><td>' . esc_html((string) $record['entity_key']) . '</td><td>' . esc_html((string) $record['status']) . '</td><td>' . esc_html((string) $record['updated_at']) . '</td></tr>';
            }
        }
        echo '</tbody></table></div>';
    }

    public static function renderEscalationsPage(): void
    {
        self::assertCapability();

        $rules = self::getEscalationRules();
        global $wpdb;
        $logsTable = $wpdb->prefix . 'cp_audit_logs';
        $logs = $wpdb->get_results("SELECT id, action, status, created_at FROM {$logsTable} WHERE action LIKE 'escalation_%' ORDER BY id DESC LIMIT 100", ARRAY_A);

        echo '<div class="wrap"><h1>' . esc_html__('Escalations', 'caringpays-care-advisor') . '</h1>';
        echo '<p>' . esc_html__('Configure ESC-01 to ESC-07 policies, thresholds, trigger keywords/patterns, approved responses, and reviewer/SLA assignment. ESC-03 is hard-locked.', 'caringpays-care-advisor') . '</p>';
        echo '<form method="post">';
        wp_nonce_field('caringpays_save_escalations', 'caringpays_escalation_nonce');
        echo '<input type="hidden" name="caringpays_admin_action" value="save_escalations" />';

        foreach ($rules as $code => $rule) {
            $readOnly = $code === EscalationCodes::ESC_03;
            echo '<h2>' . esc_html($code) . ($readOnly ? ' (Locked)' : '') . '</h2>';
            echo '<table class="form-table"><tbody>';
            echo '<tr><th>Threshold</th><td><input type="number" min="1" name="rules[' . esc_attr($code) . '][threshold]" value="' . esc_attr((string) $rule['threshold']) . '" ' . disabled($readOnly, true, false) . ' /></td></tr>';
            echo '<tr><th>Trigger Keywords/Patterns</th><td><input type="text" class="large-text" name="rules[' . esc_attr($code) . '][patterns]" value="' . esc_attr((string) $rule['patterns']) . '" ' . disabled($readOnly, true, false) . ' /></td></tr>';
            echo '<tr><th>Approved Response</th><td><textarea name="rules[' . esc_attr($code) . '][response]" rows="3" class="large-text" ' . disabled($readOnly, true, false) . '>' . esc_textarea((string) $rule['response']) . '</textarea></td></tr>';
            echo '<tr><th>Reviewer</th><td><input type="text" name="rules[' . esc_attr($code) . '][reviewer]" value="' . esc_attr((string) $rule['reviewer']) . '" ' . disabled($readOnly, true, false) . ' /></td></tr>';
            echo '<tr><th>SLA</th><td><input type="text" name="rules[' . esc_attr($code) . '][sla]" value="' . esc_attr((string) $rule['sla']) . '" ' . disabled($readOnly, true, false) . ' /></td></tr>';
            if ($readOnly) {
                echo '<tr><td colspan="2"><em>' . esc_html__('ESC-03 remains immediate stop with static approved message and no auto-resume.', 'caringpays-care-advisor') . '</em></td></tr>';
            }
            echo '</tbody></table>';
        }

        submit_button(__('Save Escalation Rules', 'caringpays-care-advisor'));
        echo '</form>';

        echo '<h2>' . esc_html__('Escalation Logs', 'caringpays-care-advisor') . '</h2>';
        echo '<table class="widefat"><thead><tr><th>ID</th><th>Action</th><th>Status</th><th>When</th></tr></thead><tbody>';
        if (empty($logs)) {
            echo '<tr><td colspan="4">' . esc_html__('No escalation logs yet.', 'caringpays-care-advisor') . '</td></tr>';
        } else {
            foreach ($logs as $log) {
                echo '<tr><td>' . esc_html((string) $log['id']) . '</td><td>' . esc_html((string) $log['action']) . '</td><td>' . esc_html((string) $log['status']) . '</td><td>' . esc_html((string) $log['created_at']) . '</td></tr>';
            }
        }
        echo '</tbody></table></div>';
    }

    public static function renderCrmIntegrationPage(): void
    {
        self::assertCapability();

        $settings = self::getGhlSettings();
        $status = (string) get_option(self::OPTION_TEST_CONNECTION_STATUS, 'not_tested');

        echo '<div class="wrap"><h1>' . esc_html__('GHL CRM Integration', 'caringpays-care-advisor') . '</h1>';
        echo '<form method="post">';
        wp_nonce_field('caringpays_save_ghl_settings', 'caringpays_ghl_nonce');
        echo '<input type="hidden" name="caringpays_admin_action" value="save_ghl_settings" />';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th><label for="api_token">API Key / Token</label></th><td><input type="password" id="api_token" name="api_token" class="regular-text" autocomplete="new-password" placeholder="' . esc_attr__('Saved (enter to replace)', 'caringpays-care-advisor') . '" /></td></tr>';
        echo '<tr><th><label for="location_id">Location / Subaccount ID</label></th><td><input type="text" id="location_id" name="location_id" class="regular-text" value="' . esc_attr((string) ($settings['location_id'] ?? '')) . '" /></td></tr>';
        echo '<tr><th><label for="pipeline_map">Pipeline/Stage Mapping (JSON)</label></th><td><textarea id="pipeline_map" name="pipeline_map" class="large-text" rows="3">' . esc_textarea((string) ($settings['pipeline_map'] ?? '{}')) . '</textarea></td></tr>';
        echo '<tr><th><label for="field_map">Contact Field Mapping (JSON)</label></th><td><textarea id="field_map" name="field_map" class="large-text" rows="3">' . esc_textarea((string) ($settings['field_map'] ?? '{}')) . '</textarea></td></tr>';
        echo '<tr><th><label for="sync_enabled">Enable Sync</label></th><td><label><input type="checkbox" id="sync_enabled" name="sync_enabled" value="1" ' . checked(! empty($settings['sync_enabled']), true, false) . ' /> ' . esc_html__('Push leads to GHL', 'caringpays-care-advisor') . '</label></td></tr>';
        echo '<tr><th><label for="retry_policy">Retry Policy</label></th><td><input type="text" id="retry_policy" name="retry_policy" class="regular-text" value="' . esc_attr((string) ($settings['retry_policy'] ?? 'exponential:3')) . '" /></td></tr>';
        echo '<tr><th><label for="sync_mode">Webhook or Direct API Mode</label></th><td><select id="sync_mode" name="sync_mode"><option value="direct_api" ' . selected((string) ($settings['sync_mode'] ?? ''), 'direct_api', false) . '>Direct API</option><option value="webhook" ' . selected((string) ($settings['sync_mode'] ?? ''), 'webhook', false) . '>Webhook</option></select></td></tr>';
        echo '</tbody></table>';
        submit_button(__('Save GHL Settings', 'caringpays-care-advisor'));
        echo '</form>';

        echo '<form method="post" style="margin-top:1rem;">';
        wp_nonce_field('caringpays_test_ghl_connection', 'caringpays_ghl_test_nonce');
        echo '<input type="hidden" name="caringpays_admin_action" value="test_ghl_connection" />';
        submit_button(__('Test Connection', 'caringpays-care-advisor'), 'secondary', 'submit', false);
        echo ' <span>' . esc_html__('Latest status:', 'caringpays-care-advisor') . ' ' . esc_html($status) . '</span>';
        echo '</form>';

        echo '<h2>' . esc_html__('Lead Push Status Logs', 'caringpays-care-advisor') . '</h2>';
        self::renderFailedSyncQueueTable();
        echo '</div>';
    }

    public static function renderSettingsPage(): void
    {
        self::assertCapability();
        echo '<div class="wrap"><h1>' . esc_html__('Settings', 'caringpays-care-advisor') . '</h1>';
        echo '<p>' . esc_html__('Operational settings are managed through Dashboard tabs and dedicated pages.', 'caringpays-care-advisor') . '</p></div>';
    }

    public static function renderResetToolsPage(): void
    {
        self::assertCapability();

        echo '<div class="wrap"><h1>' . esc_html__('Reset / Tools', 'caringpays-care-advisor') . '</h1>';
        echo '<p>' . esc_html__('Protected reset workflow with nonce, capability check, and explicit double confirmation.', 'caringpays-care-advisor') . '</p>';
        echo '<form method="post">';
        wp_nonce_field('caringpays_run_reset', 'caringpays_reset_nonce');
        echo '<input type="hidden" name="caringpays_admin_action" value="run_reset" />';
        echo '<fieldset><legend>' . esc_html__('Select reset actions', 'caringpays-care-advisor') . '</legend>';
        echo '<label><input type="checkbox" name="reset_actions[]" value="settings" /> ' . esc_html__('Clear plugin settings', 'caringpays-care-advisor') . '</label><br/>';
        echo '<label><input type="checkbox" name="reset_actions[]" value="learning_queue" /> ' . esc_html__('Clear learning queue', 'caringpays-care-advisor') . '</label><br/>';
        echo '<label><input type="checkbox" name="reset_actions[]" value="failed_sync_queue" /> ' . esc_html__('Clear failed sync queue', 'caringpays-care-advisor') . '</label><br/>';
        echo '<label><input type="checkbox" name="reset_actions[]" value="analytics" /> ' . esc_html__('Clear analytics', 'caringpays-care-advisor') . '</label><br/>';
        echo '<label><input type="checkbox" name="reset_actions[]" value="audit_logs" /> ' . esc_html__('Clear audit logs (optional)', 'caringpays-care-advisor') . '</label><br/>';
        echo '<label><input type="checkbox" name="reset_actions[]" value="esc03" /> ' . esc_html__('Reset ESC-03 protected defaults (requires separate confirmation)', 'caringpays-care-advisor') . '</label>';
        echo '</fieldset>';
        echo '<p><label><input type="checkbox" name="confirm_primary" value="1" /> ' . esc_html__('I understand this reset cannot be undone.', 'caringpays-care-advisor') . '</label></p>';
        echo '<p><label><input type="checkbox" name="confirm_secondary" value="1" /> ' . esc_html__('I confirm I am authorized to perform this reset.', 'caringpays-care-advisor') . '</label></p>';
        echo '<p><label><input type="checkbox" name="confirm_esc03" value="1" /> ' . esc_html__('I explicitly confirm ESC-03 defaults reset.', 'caringpays-care-advisor') . '</label></p>';
        submit_button(__('Run Reset', 'caringpays-care-advisor'), 'delete');
        echo '</form></div>';
    }

    public static function renderOptInPage(): void
    {
        self::assertCapability();

        $checks = self::operationalChecks();
        $enabled = (bool) get_option(self::OPTION_OPT_IN_ENABLED, false);

        echo '<div class="wrap"><h1>' . esc_html__('Opt In / Onboarding', 'caringpays-care-advisor') . '</h1>';
        echo '<p>' . esc_html__('Enable operational mode after validating required setup checks.', 'caringpays-care-advisor') . '</p>';
        echo '<ul>';
        foreach ($checks as $label => $ok) {
            echo '<li>' . ($ok ? '✅' : '❌') . ' ' . esc_html($label) . '</li>';
        }
        echo '</ul>';

        echo '<p><strong>' . esc_html__('Operational mode status:', 'caringpays-care-advisor') . '</strong> ' . ($enabled ? esc_html__('Enabled', 'caringpays-care-advisor') : esc_html__('Disabled', 'caringpays-care-advisor')) . '</p>';

        echo '<form method="post">';
        wp_nonce_field('caringpays_opt_in_enable', 'caringpays_optin_nonce');
        echo '<input type="hidden" name="caringpays_admin_action" value="opt_in_enable" />';
        submit_button(__('Enable Plugin Workflow', 'caringpays-care-advisor'));
        echo '</form></div>';
    }

    private static function saveSourceOfTruth(): void
    {
        check_admin_referer('caringpays_save_source_of_truth', 'caringpays_source_nonce');

        global $wpdb;
        $table = $wpdb->prefix . 'cp_source_of_truth';

        $entityType = sanitize_key((string) wp_unslash($_POST['entity_type'] ?? ''));
        $entityKey = sanitize_text_field((string) wp_unslash($_POST['entity_key'] ?? ''));
        $status = sanitize_key((string) wp_unslash($_POST['status'] ?? 'draft'));
        $payload = sanitize_textarea_field((string) wp_unslash($_POST['payload'] ?? ''));

        if ($entityType === '' || $entityKey === '' || $payload === '') {
            wp_safe_redirect(add_query_arg(['page' => self::MENU_SLUG_SOURCE_OF_TRUTH, 'saved' => '0'], admin_url('admin.php')));
            exit;
        }

        $now = current_time('mysql');
        $wpdb->replace(
            $table,
            [
                'entity_type' => $entityType,
                'entity_key' => $entityKey,
                'state' => 'approved_context',
                'status' => in_array($status, ['draft', 'approved', 'published'], true) ? $status : 'draft',
                'payload' => wp_json_encode(['content' => $payload], JSON_UNESCAPED_SLASHES),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        wp_safe_redirect(add_query_arg(['page' => self::MENU_SLUG_SOURCE_OF_TRUTH, 'saved' => '1'], admin_url('admin.php')));
        exit;
    }

    private static function saveEscalations(): void
    {
        check_admin_referer('caringpays_save_escalations', 'caringpays_escalation_nonce');

        $incoming = $_POST['rules'] ?? [];
        if (! is_array($incoming)) {
            $incoming = [];
        }

        $rules = self::defaultEscalationRules();

        foreach ($rules as $code => $defaults) {
            if ($code === EscalationCodes::ESC_03) {
                $rules[$code] = self::esc03LockedRule();
                continue;
            }

            $raw = $incoming[$code] ?? [];
            if (! is_array($raw)) {
                $raw = [];
            }

            $rules[$code] = [
                'threshold' => max(1, (int) ($raw['threshold'] ?? $defaults['threshold'])),
                'patterns' => sanitize_text_field((string) ($raw['patterns'] ?? $defaults['patterns'])),
                'response' => sanitize_textarea_field((string) ($raw['response'] ?? $defaults['response'])),
                'reviewer' => sanitize_text_field((string) ($raw['reviewer'] ?? $defaults['reviewer'])),
                'sla' => sanitize_text_field((string) ($raw['sla'] ?? $defaults['sla'])),
            ];
        }

        update_option(self::OPTION_ESCALATION_RULES, $rules, false);

        wp_safe_redirect(add_query_arg(['page' => self::MENU_SLUG_ESCALATIONS, 'saved' => '1'], admin_url('admin.php')));
        exit;
    }

    private static function saveGhlSettings(): void
    {
        check_admin_referer('caringpays_save_ghl_settings', 'caringpays_ghl_nonce');

        $existing = self::getGhlSettings();
        $token = (string) wp_unslash($_POST['api_token'] ?? '');

        if ($token !== '') {
            update_option(self::OPTION_GHL_SECRET, self::encryptSecret($token), false);
        }

        $syncMode = (string) wp_unslash($_POST['sync_mode'] ?? '');

        $settings = [
            'location_id' => sanitize_text_field((string) wp_unslash($_POST['location_id'] ?? '')),
            'pipeline_map' => sanitize_textarea_field((string) wp_unslash($_POST['pipeline_map'] ?? '{}')),
            'field_map' => sanitize_textarea_field((string) wp_unslash($_POST['field_map'] ?? '{}')),
            'sync_enabled' => isset($_POST['sync_enabled']) ? 1 : 0,
            'retry_policy' => sanitize_text_field((string) wp_unslash($_POST['retry_policy'] ?? 'exponential:3')),
            'sync_mode' => in_array($syncMode, ['webhook', 'direct_api'], true) ? $syncMode : 'direct_api',
            'updated_at' => current_time('mysql'),
        ];

        update_option(self::OPTION_GHL_SETTINGS, array_merge($existing, $settings), false);

        wp_safe_redirect(add_query_arg(['page' => self::MENU_SLUG_CRM, 'saved' => '1'], admin_url('admin.php')));
        exit;
    }

    private static function testGhlConnection(): void
    {
        check_admin_referer('caringpays_test_ghl_connection', 'caringpays_ghl_test_nonce');

        $token = self::decryptSecret((string) get_option(self::OPTION_GHL_SECRET, ''));
        $settings = self::getGhlSettings();

        $status = ($token !== '' && ! empty($settings['location_id'])) ? 'success_' . gmdate('c') : 'failed_missing_credentials_' . gmdate('c');
        update_option(self::OPTION_TEST_CONNECTION_STATUS, $status, false);

        wp_safe_redirect(add_query_arg(['page' => self::MENU_SLUG_CRM, 'tested' => '1'], admin_url('admin.php')));
        exit;
    }

    private static function optInEnable(): void
    {
        check_admin_referer('caringpays_opt_in_enable', 'caringpays_optin_nonce');

        $checks = self::operationalChecks();
        $allPass = ! in_array(false, $checks, true);

        if ($allPass) {
            update_option(self::OPTION_OPT_IN_ENABLED, 1, false);
            wp_safe_redirect(add_query_arg(['page' => self::MENU_SLUG_OPT_IN, 'enabled' => '1'], admin_url('admin.php')));
            exit;
        }

        wp_safe_redirect(add_query_arg(['page' => self::MENU_SLUG_OPT_IN, 'enabled' => '0'], admin_url('admin.php')));
        exit;
    }

    private static function runResetWorkflow(): void
    {
        check_admin_referer('caringpays_run_reset', 'caringpays_reset_nonce');

        $primary = isset($_POST['confirm_primary']);
        $secondary = isset($_POST['confirm_secondary']);
        if (! $primary || ! $secondary) {
            wp_safe_redirect(add_query_arg(['page' => self::MENU_SLUG_RESET, 'reset' => 'confirm_failed'], admin_url('admin.php')));
            exit;
        }

        $actions = $_POST['reset_actions'] ?? [];
        if (! is_array($actions)) {
            $actions = [];
        }

        global $wpdb;
        $prefix = $wpdb->prefix;

        if (in_array('settings', $actions, true)) {
            delete_option(self::OPTION_GHL_SETTINGS);
            delete_option(self::OPTION_GHL_SECRET);
            delete_option(self::OPTION_OPT_IN_ENABLED);
        }

        if (in_array('learning_queue', $actions, true)) {
            $wpdb->query("TRUNCATE TABLE {$prefix}cp_optimization_queue");
        }

        if (in_array('failed_sync_queue', $actions, true)) {
            $wpdb->query("TRUNCATE TABLE {$prefix}cp_failed_sync_queue");
        }

        if (in_array('analytics', $actions, true)) {
            delete_option('caringpays_analytics_snapshot');
            delete_option('caringpays_analytics_counters');
        }

        if (in_array('audit_logs', $actions, true)) {
            $wpdb->query("TRUNCATE TABLE {$prefix}cp_audit_logs");
        }

        if (in_array('esc03', $actions, true) && isset($_POST['confirm_esc03'])) {
            update_option(EscalationPolicy::protectedEscalationOptionKey(), self::esc03LockedRule()['response'], false);
        }

        wp_safe_redirect(add_query_arg(['page' => self::MENU_SLUG_RESET, 'reset' => 'done'], admin_url('admin.php')));
        exit;
    }

    /**
     * @return array<string,bool>
     */
    private static function operationalChecks(): array
    {
        global $wpdb;
        $prefix = $wpdb->prefix;

        $dbTables = [
            $prefix . 'cp_source_of_truth',
            $prefix . 'cp_optimization_queue',
            $prefix . 'cp_failed_sync_queue',
        ];

        $tablesExist = true;
        foreach ($dbTables as $table) {
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
            if ($exists !== $table) {
                $tablesExist = false;
                break;
            }
        }

        $approvedCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}cp_source_of_truth WHERE status IN ('approved', 'published')");
        $settings = self::getGhlSettings();
        $token = self::decryptSecret((string) get_option(self::OPTION_GHL_SECRET, ''));

        return [
            'Database tables exist' => $tablesExist,
            'Source of Truth has >= 1 approved/published item' => $approvedCount > 0,
            'GHL integration configured' => $token !== '' && ! empty($settings['location_id']),
            'Required settings complete' => ! empty($settings['sync_mode']) && ! empty($settings['retry_policy']),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function getGhlSettings(): array
    {
        $settings = get_option(self::OPTION_GHL_SETTINGS, []);

        return is_array($settings) ? $settings : [];
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private static function getEscalationRules(): array
    {
        $saved = get_option(self::OPTION_ESCALATION_RULES, []);
        $defaults = self::defaultEscalationRules();

        if (! is_array($saved)) {
            return $defaults;
        }

        $rules = array_merge($defaults, $saved);
        $rules[EscalationCodes::ESC_03] = self::esc03LockedRule();

        return $rules;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private static function defaultEscalationRules(): array
    {
        $codes = [
            EscalationCodes::ESC_01,
            EscalationCodes::ESC_02,
            EscalationCodes::ESC_03,
            EscalationCodes::ESC_04,
            EscalationCodes::ESC_05,
            EscalationCodes::ESC_06,
            EscalationCodes::ESC_07,
        ];

        $rules = [];
        foreach ($codes as $code) {
            $rules[$code] = [
                'threshold' => 1,
                'patterns' => '',
                'response' => 'Escalation acknowledged. A reviewer will follow up.',
                'reviewer' => 'Operations Team',
                'sla' => '24h',
            ];
        }

        $rules[EscalationCodes::ESC_03] = self::esc03LockedRule();

        return $rules;
    }

    /**
     * @return array<string,mixed>
     */
    private static function esc03LockedRule(): array
    {
        return [
            'threshold' => 1,
            'patterns' => 'emergency, self-harm, crisis',
            'response' => 'I need to stop here and immediately route this to a human specialist. If this is an emergency, call 911 now.',
            'reviewer' => 'Emergency Reviewer',
            'sla' => 'immediate',
            'immediate_stop' => true,
            'auto_resume' => false,
            'static_message_only' => true,
        ];
    }

    private static function renderFailedSyncQueueTable(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'cp_failed_sync_queue';
        $items = $wpdb->get_results("SELECT id, status, last_error, created_at FROM {$table} ORDER BY id DESC LIMIT 100", ARRAY_A);

        echo '<table class="widefat"><thead><tr><th>ID</th><th>Status</th><th>Error</th><th>Created</th></tr></thead><tbody>';
        if (empty($items)) {
            echo '<tr><td colspan="4">' . esc_html__('No failed sync records in queue.', 'caringpays-care-advisor') . '</td></tr>';
        } else {
            foreach ($items as $item) {
                echo '<tr><td>' . esc_html((string) $item['id']) . '</td><td>' . esc_html((string) $item['status']) . '</td><td>' . esc_html((string) $item['last_error']) . '</td><td>' . esc_html((string) $item['created_at']) . '</td></tr>';
            }
        }
        echo '</tbody></table>';
    }

    private static function adminPageUrl(string $slug): string
    {
        return admin_url('admin.php?page=' . $slug);
    }

    private static function assertCapability(): void
    {
        if (! current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to access this page.', 'caringpays-care-advisor'));
        }
    }

    private static function encryptSecret(string $plainText): string
    {
        $key = hash('sha256', wp_salt('auth'));
        $iv = substr(hash('sha256', wp_salt('nonce')), 0, 16);
        $encrypted = openssl_encrypt($plainText, 'AES-256-CBC', $key, 0, $iv);

        return is_string($encrypted) ? $encrypted : '';
    }

    private static function decryptSecret(string $cipherText): string
    {
        if ($cipherText === '') {
            return '';
        }

        $key = hash('sha256', wp_salt('auth'));
        $iv = substr(hash('sha256', wp_salt('nonce')), 0, 16);
        $decrypted = openssl_decrypt($cipherText, 'AES-256-CBC', $key, 0, $iv);

        return is_string($decrypted) ? $decrypted : '';
    }
}
