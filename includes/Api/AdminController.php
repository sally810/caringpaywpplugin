<?php

namespace CaringPays\CareAdvisor\Api;

use CaringPays\CareAdvisor\Database\OptimizationQueueRepository;
use CaringPays\CareAdvisor\Security\RequestSanitizer;
use WP_REST_Request;
use WP_REST_Response;

final class AdminController
{
    public static function sourceOfTruth(WP_REST_Request $request): WP_REST_Response
    {
        $entityType = RequestSanitizer::key($request->get_param('entity_type'));
        $entityKey = RequestSanitizer::text($request->get_param('entity_key'));

        $response = [
            'ok' => true,
            'data' => [
                'entity_type' => $entityType,
                'entity_key' => $entityKey,
                'records' => [],
            ],
        ];

        return new WP_REST_Response($response, 200);
    }

    public static function optimize(WP_REST_Request $request): WP_REST_Response
    {
        $optimizationType = RequestSanitizer::key($request->get_param('optimization_type'));
        $sessionId = RequestSanitizer::integer($request->get_param('session_id'));
        $state = RequestSanitizer::text($request->get_param('state'));

        $payload = $request->get_param('payload');
        if (! is_array($payload)) {
            $payload = [];
        }

        $queued = OptimizationQueueRepository::enqueue(
            $payload,
            $optimizationType,
            $sessionId > 0 ? $sessionId : null,
            $state !== '' ? $state : null
        );

        $response = [
            'ok' => $queued,
            'data' => [
                'queued' => $queued,
                'optimization_type' => $optimizationType,
                'session_id' => $sessionId,
            ],
        ];

        return new WP_REST_Response($response, $queued ? 202 : 500);
    }
}
