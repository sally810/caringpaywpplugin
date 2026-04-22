<?php

namespace CaringPays\CareAdvisor\Grounding;

use CaringPays\CareAdvisor\Security\RequestSanitizer;

final class GroundedResponseService
{
    private EligibilityMatrixService $eligibilityMatrixService;

    public function __construct(?EligibilityMatrixService $eligibilityMatrixService = null)
    {
        $this->eligibilityMatrixService = $eligibilityMatrixService ?? new EligibilityMatrixService();
    }

    /**
     * @return array<string,mixed>
     */
    public function compose(string $sessionToken, string $state, string $message): array
    {
        $answers = $this->loadEligibilityAnswersForSession($sessionToken);
        $eligibility = $this->eligibilityMatrixService->evaluate($state, $answers);
        $intents = $this->detectIntents($message);

        if ($intents !== [] && ! $this->hasEligibleIntent($intents, $eligibility)) {
            return [
                'ok' => false,
                'eligibility' => $eligibility,
                'fragments' => [],
                'message' => '',
            ];
        }

        $fragments = $this->selectApprovedFragments($state, $message, $intents, $eligibility);
        if ($fragments === []) {
            return [
                'ok' => false,
                'eligibility' => $eligibility,
                'fragments' => [],
                'message' => '',
            ];
        }

        return [
            'ok' => true,
            'eligibility' => $eligibility,
            'fragments' => $fragments,
            'message' => implode("\n\n", $fragments),
        ];
    }

    /**
     * @param string[] $intents
     * @param array<string,bool> $eligibility
     */
    private function hasEligibleIntent(array $intents, array $eligibility): bool
    {
        foreach ($intents as $intent) {
            if (($eligibility[$intent] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string[] $intents
     * @param array<string,bool> $eligibility
     * @return string[]
     */
    private function selectApprovedFragments(string $state, string $message, array $intents, array $eligibility): array
    {
        global $wpdb;

        if (! isset($wpdb)) {
            return [];
        }

        $table = $wpdb->prefix . 'cp_source_of_truth';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT payload
            FROM {$table}
            WHERE status = %s
              AND (state = %s OR state = %s)
            ORDER BY updated_at DESC, id DESC",
            'active',
            strtolower($state),
            'all'
        ));

        if (! is_array($rows) || $rows === []) {
            return [];
        }

        $tokens = $this->tokenize($message);
        $picked = [];

        foreach ($rows as $row) {
            if (! is_object($row) || ! isset($row->payload) || ! is_string($row->payload)) {
                continue;
            }

            foreach ($this->extractFragments($row->payload) as $fragment) {
                $text = RequestSanitizer::text($fragment['text'] ?? '');
                if ($text === '') {
                    continue;
                }

                $group = RequestSanitizer::key($fragment['eligibility_group'] ?? '');
                if ($group !== '' && ($eligibility[$group] ?? false) !== true) {
                    continue;
                }

                if ($intents !== [] && $group !== '' && ! in_array($group, $intents, true)) {
                    continue;
                }

                if (! $this->matchesFragment($tokens, $fragment)) {
                    continue;
                }

                if (in_array($text, $picked, true)) {
                    continue;
                }

                $picked[] = $text;
                if (count($picked) >= 3) {
                    return $picked;
                }
            }
        }

        return $picked;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function extractFragments(string $payload): array
    {
        $decoded = json_decode($payload, true);
        if (! is_array($decoded)) {
            return [
                [
                    'text' => $payload,
                    'keywords' => [],
                ],
            ];
        }

        $fragments = $decoded['fragments'] ?? null;
        if (! is_array($fragments)) {
            if (isset($decoded['text']) && is_string($decoded['text'])) {
                return [[
                    'text' => $decoded['text'],
                    'keywords' => $decoded['keywords'] ?? [],
                    'eligibility_group' => $decoded['eligibility_group'] ?? '',
                ]];
            }

            return [];
        }

        $normalized = [];
        foreach ($fragments as $fragment) {
            if (! is_array($fragment)) {
                continue;
            }

            $normalized[] = [
                'text' => $fragment['text'] ?? '',
                'keywords' => $fragment['keywords'] ?? [],
                'eligibility_group' => $fragment['eligibility_group'] ?? '',
            ];
        }

        return $normalized;
    }

    /**
     * @param string[] $tokens
     * @param array<string,mixed> $fragment
     */
    private function matchesFragment(array $tokens, array $fragment): bool
    {
        $keywords = $fragment['keywords'] ?? [];
        if (! is_array($keywords) || $keywords === []) {
            return true;
        }

        foreach ($keywords as $keyword) {
            $normalizedKeyword = RequestSanitizer::key((string) $keyword);
            if ($normalizedKeyword !== '' && in_array($normalizedKeyword, $tokens, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string[]
     */
    private function tokenize(string $message): array
    {
        $parts = preg_split('/[^a-z0-9]+/', strtolower($message));
        if (! is_array($parts)) {
            return [];
        }

        $tokens = [];
        foreach ($parts as $part) {
            $token = RequestSanitizer::key($part);
            if ($token !== '') {
                $tokens[] = $token;
            }
        }

        return array_values(array_unique($tokens));
    }

    /**
     * @return string[]
     */
    private function detectIntents(string $message): array
    {
        $normalized = strtolower($message);
        $intents = [];

        if (str_contains($normalized, 'ccsp') || (str_contains($normalized, 'georgia') && str_contains($normalized, 'nhloc'))) {
            $intents[] = 'ga_ccsp';
        }

        if (str_contains($normalized, 'source') && str_contains($normalized, 'georgia')) {
            $intents[] = 'ga_source';
        }

        if (str_contains($normalized, 'afc') && (str_contains($normalized, 'massachusetts') || str_contains($normalized, 'ma'))) {
            $intents[] = 'ma_afc';
        }

        return array_values(array_unique($intents));
    }

    /**
     * @return array<string,string>
     */
    private function loadEligibilityAnswersForSession(string $sessionToken): array
    {
        global $wpdb;

        if (! isset($wpdb) || $sessionToken === '') {
            return [];
        }

        $sessionTable = $wpdb->prefix . 'cp_sessions';
        $leadTable = $wpdb->prefix . 'cp_leads';

        $payload = $wpdb->get_var($wpdb->prepare(
            "SELECT l.lead_payload
            FROM {$leadTable} l
            INNER JOIN {$sessionTable} s ON s.id = l.session_id
            WHERE s.session_uuid = %s
            ORDER BY l.updated_at DESC, l.id DESC
            LIMIT 1",
            $sessionToken
        ));

        if (! is_string($payload) || $payload === '') {
            return [];
        }

        $decoded = json_decode($payload, true);
        if (! is_array($decoded) || ! isset($decoded['eligibility_answers']) || ! is_array($decoded['eligibility_answers'])) {
            return [];
        }

        $answers = [];
        foreach ($decoded['eligibility_answers'] as $key => $value) {
            $normalizedKey = RequestSanitizer::key((string) $key);
            $normalizedValue = RequestSanitizer::text((string) $value);

            if ($normalizedKey === '' || $normalizedValue === '') {
                continue;
            }

            $answers[$normalizedKey] = strtolower(trim($normalizedValue));
        }

        return $answers;
    }
}
