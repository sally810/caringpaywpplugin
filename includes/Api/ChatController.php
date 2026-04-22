<?php

namespace CaringPays\CareAdvisor\Api;

use CaringPays\CareAdvisor\Security\RequestSanitizer;
use WP_REST_Request;
use WP_REST_Response;

final class ChatController
{
    public static function startSession(WP_REST_Request $request): WP_REST_Response
    {
        $state = RequestSanitizer::text($request->get_param('state'));
        $entryPoint = RequestSanitizer::key($request->get_param('entry_point'));

        $response = [
            'ok' => true,
            'data' => [
                'session_token' => RequestSanitizer::text($request->get_param('session_token')),
                'state' => $state,
                'entry_point' => $entryPoint,
                'status' => 'active',
            ],
        ];

        return new WP_REST_Response($response, 200);
    }

    public static function message(WP_REST_Request $request): WP_REST_Response
    {
        $message = RequestSanitizer::html($request->get_param('message'));
        $turnIndex = RequestSanitizer::integer($request->get_param('turn_index'));

        $response = [
            'ok' => true,
            'data' => [
                'turn_index' => $turnIndex,
                'role' => 'user',
                'message' => $message,
            ],
        ];

        return new WP_REST_Response($response, 200);
    }

    public static function history(WP_REST_Request $request): WP_REST_Response
    {
        $sessionToken = RequestSanitizer::text($request->get_param('session_token'));
        $limit = RequestSanitizer::integer($request->get_param('limit'));
        if ($limit <= 0) {
            $limit = 20;
        }

        $response = [
            'ok' => true,
            'data' => [
                'session_token' => $sessionToken,
                'limit' => $limit,
                'items' => [],
            ],
        ];

        return new WP_REST_Response($response, 200);
    }
}
