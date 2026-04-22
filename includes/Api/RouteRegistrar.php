<?php

namespace CaringPays\CareAdvisor\Api;

use CaringPays\CareAdvisor\Security\AdminAccessService;
use CaringPays\CareAdvisor\Security\FrontendAccessService;

final class RouteRegistrar
{
    private const NAMESPACE = 'caringpays-care-advisor/v1';

    public static function boot(): void
    {
        add_action('rest_api_init', [self::class, 'registerRoutes']);
    }

    public static function registerRoutes(): void
    {
        register_rest_route(
            self::NAMESPACE,
            '/chat/start-session',
            [
                'methods' => 'POST',
                'callback' => [ChatController::class, 'startSession'],
                'permission_callback' => [FrontendAccessService::class, 'permissionCallback'],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/chat/message',
            [
                'methods' => 'POST',
                'callback' => [ChatController::class, 'message'],
                'permission_callback' => [FrontendAccessService::class, 'permissionCallback'],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/chat/history',
            [
                'methods' => 'GET',
                'callback' => [ChatController::class, 'history'],
                'permission_callback' => [FrontendAccessService::class, 'permissionCallback'],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/admin/source-of-truth',
            [
                'methods' => 'GET',
                'callback' => [AdminController::class, 'sourceOfTruth'],
                'permission_callback' => [AdminAccessService::class, 'permissionCallback'],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/admin/optimize',
            [
                'methods' => 'POST',
                'callback' => [AdminController::class, 'optimize'],
                'permission_callback' => [AdminAccessService::class, 'permissionCallback'],
            ]
        );
    }
}
