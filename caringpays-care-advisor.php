<?php
/**
 * Plugin Name: CaringPays Care Advisor
 * Plugin URI:  https://caringpays.com/
 * Description: Bootstrap and runtime initialization for the CaringPays Care Advisor plugin.
 * Version:     0.1.0
 * Author:      CaringPays
 * Text Domain: caringpays-care-advisor
 * Domain Path: /languages
 * Requires PHP: 8.0
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('CARINGPAYS_CARE_ADVISOR_FILE')) {
    define('CARINGPAYS_CARE_ADVISOR_FILE', __FILE__);
}

$autoload = __DIR__ . '/vendor/autoload.php';
if (! file_exists($autoload)) {
    return;
}

require_once $autoload;

add_action('plugins_loaded', [\CaringPays\CareAdvisor\Core\Plugin::class, 'pluginsLoaded']);
add_action('init', [\CaringPays\CareAdvisor\Core\Plugin::class, 'init']);
