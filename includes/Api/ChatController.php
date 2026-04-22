<?php

namespace CaringPays\CareAdvisor\Api;

use CaringPays\CareAdvisor\Security\RequestSanitizer;
use WP_REST_Request;
use WP_REST_Response;

final class ChatController
{
    private const REQUIRED_UTM_KEYS = [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
    ];

    public static function startSession(WP_REST_Request $request): WP_REST_Response
    {
        $state = RequestSanitizer::text($request->get_param('state'));
        $entryPoint = RequestSanitizer::key($request->get_param('entry_point'));
        $sessionToken = RequestSanitizer::text($request->get_param('session_token'));

        if ($sessionToken === '') {
            $sessionToken = wp_generate_uuid4();
        }

        $eligibilityAnswers = self::normalizeEligibilityAnswers($request->get_param('eligibility_answers'));
        $consentAccepted = (bool) $request->get_param('consent_accepted');

        if ($state === '' || $eligibilityAnswers === [] || ! $consentAccepted) {
            return new WP_REST_Response(
                [
                    'ok' => false,
                    'error' => 'onboarding_required',
                    'message' => 'State selection, eligibility screening, and digital consent are required before AI interaction.',
                ],
                422
            );
        }

        $utm = self::normalizeUtm($request->get_param('utm'));
        $sessionId = self::createSession($sessionToken, $state);

        if ($sessionId <= 0 || ! self::persistLeadConsent($sessionId, $state, $entryPoint, $utm, $eligibilityAnswers)) {
            return new WP_REST_Response(
                [
                    'ok' => false,
                    'error' => 'session_persistence_failed',
                ],
                500
            );
        }

        $response = [
            'ok' => true,
            'data' => [
                'session_token' => $sessionToken,
                'state' => $state,
                'entry_point' => $entryPoint,
                'status' => 'active',
                'onboarding_complete' => true,
                'utm' => $utm,
            ],
        ];

        return new WP_REST_Response($response, 200);
    }

    public static function message(WP_REST_Request $request): WP_REST_Response
    {
        $sessionToken = RequestSanitizer::text($request->get_param('session_token'));

        if (! self::hasConsentedLead($sessionToken)) {
            return new WP_REST_Response(
                [
                    'ok' => false,
                    'error' => 'onboarding_required',
                    'message' => 'Complete onboarding and consent before sending AI messages.',
                ],
                403
            );
        }

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

    private static function normalizeUtm(mixed $utmRaw): array
    {
        $utm = is_array($utmRaw) ? $utmRaw : [];
        $normalized = [];

        foreach (self::REQUIRED_UTM_KEYS as $key) {
            $value = isset($utm[$key]) ? RequestSanitizer::text($utm[$key]) : '';
            $normalized[$key] = self::normalizeValue($value);
        }

        return $normalized;
    }

    private static function normalizeEligibilityAnswers(mixed $answersRaw): array
    {
        if (! is_array($answersRaw)) {
            return [];
        }

        $normalized = [];
        foreach ($answersRaw as $question => $answer) {
            $questionKey = RequestSanitizer::key($question);
            $answerValue = self::normalizeValue(RequestSanitizer::text($answer));

            if ($questionKey === '' || $answerValue === '') {
                continue;
            }

            $normalized[$questionKey] = $answerValue;
        }

        return $normalized;
    }

    private static function normalizeValue(string $value): string
    {
        $value = strtolower($value);
        $value = preg_replace('/\s+/', '_', $value);

        return trim((string) $value, '_');
    }

    private static function createSession(string $sessionToken, string $state): int
    {
        global $wpdb;

        if (! isset($wpdb)) {
            return 0;
        }

        $sessionTable = $wpdb->prefix . 'cp_sessions';
        $existingId = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$sessionTable} WHERE session_uuid = %s LIMIT 1",
            $sessionToken
        ));

        if ($existingId > 0) {
            $wpdb->update(
                $sessionTable,
                [
                    'state' => $state,
                    'status' => 'active',
                    'updated_at' => current_time('mysql'),
                ],
                ['id' => $existingId],
                ['%s', '%s', '%s'],
                ['%d']
            );

            return $existingId;
        }

        $timestamp = current_time('mysql');
        $inserted = $wpdb->insert(
            $sessionTable,
            [
                'session_uuid' => $sessionToken,
                'state' => $state,
                'status' => 'active',
                'started_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            ['%s', '%s', '%s', '%s', '%s']
        );

        return $inserted ? (int) $wpdb->insert_id : 0;
    }

    private static function persistLeadConsent(
        int $sessionId,
        string $state,
        string $entryPoint,
        array $utm,
        array $eligibilityAnswers
    ): bool {
        global $wpdb;

        if (! isset($wpdb)) {
            return false;
        }

        $leadTable = $wpdb->prefix . 'cp_leads';
        $timestamp = current_time('mysql');
        $email = 'pending+' . $sessionId . '@caringpays.local';
        $payload = wp_json_encode([
            'entry_mode' => $entryPoint,
            'utm' => $utm,
            'eligibility_answers' => $eligibilityAnswers,
            'digital_consent' => [
                'accepted' => true,
                'accepted_at' => $timestamp,
            ],
        ]);

        $existingId = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$leadTable} WHERE session_id = %d LIMIT 1",
            $sessionId
        ));

        if ($existingId > 0) {
            return (bool) $wpdb->update(
                $leadTable,
                [
                    'state' => $state,
                    'status' => 'consented',
                    'lead_payload' => $payload,
                    'updated_at' => $timestamp,
                ],
                ['id' => $existingId],
                ['%s', '%s', '%s', '%s'],
                ['%d']
            );
        }

        return (bool) $wpdb->insert(
            $leadTable,
            [
                'session_id' => $sessionId,
                'email' => $email,
                'state' => $state,
                'status' => 'consented',
                'lead_payload' => $payload,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );
    }

    private static function hasConsentedLead(string $sessionToken): bool
    {
        global $wpdb;

        if (! isset($wpdb) || $sessionToken === '') {
            return false;
        }

        $sessionTable = $wpdb->prefix . 'cp_sessions';
        $leadTable = $wpdb->prefix . 'cp_leads';

        $consented = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT l.id
            FROM {$leadTable} l
            INNER JOIN {$sessionTable} s ON s.id = l.session_id
            WHERE s.session_uuid = %s
              AND l.status = %s
            LIMIT 1",
            $sessionToken,
            'consented'
        ));

        return $consented > 0;
    }
}
