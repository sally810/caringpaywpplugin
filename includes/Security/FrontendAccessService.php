<?php

namespace CaringPays\CareAdvisor\Security;

use WP_Error;
use WP_REST_Request;

final class FrontendAccessService
{
    public static function permissionCallback(WP_REST_Request $request): bool|WP_Error
    {
        $nonceValidation = self::validateNonce($request);
        if ($nonceValidation instanceof WP_Error) {
            return $nonceValidation;
        }

        $tokenValidation = self::validateActiveSessionToken($request);
        if ($tokenValidation instanceof WP_Error) {
            return $tokenValidation;
        }

        return true;
    }

    public static function validateNonce(WP_REST_Request $request): true|WP_Error
    {
        $nonce = (string) $request->get_header('X-WP-Nonce');
        if ($nonce === '') {
            $nonce = (string) $request->get_param('_wpnonce');
        }

        if ($nonce === '' || wp_verify_nonce($nonce, 'wp_rest') !== 1) {
            return new WP_Error(
                'cp_invalid_nonce',
                __('Invalid or missing request nonce.', 'caringpays-care-advisor'),
                ['status' => 403]
            );
        }

        return true;
    }

    public static function validateActiveSessionToken(WP_REST_Request $request): true|WP_Error
    {
        global $wpdb;

        if (! isset($wpdb)) {
            return new WP_Error(
                'cp_missing_database',
                __('Unable to validate session at this time.', 'caringpays-care-advisor'),
                ['status' => 500]
            );
        }

        $token = (string) $request->get_header('X-CaringPays-Session-Token');
        if ($token === '') {
            $token = (string) $request->get_param('session_token');
        }

        $token = RequestSanitizer::text($token);

        if ($token === '') {
            return new WP_Error(
                'cp_missing_session_token',
                __('Session token is required.', 'caringpays-care-advisor'),
                ['status' => 401]
            );
        }

        $table = $wpdb->prefix . 'cp_sessions';

        $sessionId = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE session_uuid = %s AND status = %s LIMIT 1",
                $token,
                'active'
            )
        );

        if ($sessionId === null) {
            return new WP_Error(
                'cp_inactive_session_token',
                __('Invalid or inactive session token.', 'caringpays-care-advisor'),
                ['status' => 401]
            );
        }

        return true;
    }
}
