<?php

namespace CaringPays\CareAdvisor\Core;

final class Plugin
{
    private static ?self $instance = null;

    private bool $environmentReady = false;

    private function __construct()
    {
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function pluginsLoaded(): void
    {
        self::instance()->runEnvironmentChecks();
    }

    public static function init(): void
    {
        self::instance()->bootRuntime();
    }

    private function runEnvironmentChecks(): void
    {
        if (version_compare(PHP_VERSION, '8.0', '<')) {
            return;
        }

        if (! defined('ABSPATH')) {
            return;
        }

        $this->environmentReady = true;
    }

    private function bootRuntime(): void
    {
        if (! $this->environmentReady) {
            return;
        }

        $this->registerRoutes();
        $this->loadLocalization();
        $this->performEdgeHandshake();
    }

    private function registerRoutes(): void
    {
        /**
         * Runtime hook for API route registration.
         *
         * Plugins/themes can hook into this action to register REST routes.
         */
        do_action('caringpays_care_advisor_register_routes');
    }

    private function loadLocalization(): void
    {
        load_plugin_textdomain(
            'caringpays-care-advisor',
            false,
            dirname(plugin_basename(CARINGPAYS_CARE_ADVISOR_FILE)) . '/languages'
        );
    }

    private function performEdgeHandshake(): void
    {
        /**
         * Runtime hook for edge service handshakes.
         *
         * Plugins/themes can hook into this action to perform edge/runtime sync work.
         */
        do_action('caringpays_care_advisor_edge_handshake');
    }
}
