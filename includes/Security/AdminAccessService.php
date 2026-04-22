<?php

namespace CaringPays\CareAdvisor\Security;

use WP_Error;
use WP_REST_Request;

final class AdminAccessService
{
    public static function permissionCallback(WP_REST_Request $request): bool|WP_Error
    {
        unset($request);

        if (current_user_can('manage_options')) {
            return true;
        }

        return new WP_Error(
            'cp_forbidden_admin_route',
            __('You do not have permission to access this route.', 'caringpays-care-advisor'),
            ['status' => 403]
        );
    }
}
