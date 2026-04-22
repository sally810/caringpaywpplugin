<?php

namespace CaringPays\CareAdvisor\Frontend;

final class WidgetShortcode
{
    private const SHORTCODE = 'caringpays_chat';
    private const SCRIPT_HANDLE = 'caringpays-care-advisor-widget';
    private const STYLE_HANDLE = 'caringpays-care-advisor-widget';

    public static function boot(): void
    {
        add_shortcode(self::SHORTCODE, [self::class, 'render']);
        add_action('wp_enqueue_scripts', [self::class, 'registerAssets']);
    }

    public static function registerAssets(): void
    {
        $baseUrl = plugin_dir_url(CARINGPAYS_CARE_ADVISOR_FILE);

        wp_register_script(
            self::SCRIPT_HANDLE,
            $baseUrl . 'public/widget.js',
            ['wp-element'],
            filemtime(plugin_dir_path(CARINGPAYS_CARE_ADVISOR_FILE) . 'public/widget.js'),
            true
        );

        wp_register_style(
            self::STYLE_HANDLE,
            $baseUrl . 'public/widget.css',
            [],
            filemtime(plugin_dir_path(CARINGPAYS_CARE_ADVISOR_FILE) . 'public/widget.css')
        );
    }

    public static function render(): string
    {
        wp_enqueue_script(self::SCRIPT_HANDLE);
        wp_enqueue_style(self::STYLE_HANDLE);

        wp_localize_script(
            self::SCRIPT_HANDLE,
            'CaringPaysChatConfig',
            [
                'apiBase' => esc_url_raw(rest_url('caringpays-care-advisor/v1')),
                'nonce' => wp_create_nonce('wp_rest'),
                'defaultEntryMode' => 'text',
            ]
        );

        return '<div id="caringpays-chat-root" class="caringpays-chat-root"></div>';
    }
}
