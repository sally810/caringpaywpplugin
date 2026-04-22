<?php

namespace CaringPays\CareAdvisor\Database;

final class OptimizationQueueRepository
{
    private const PII_KEYS = [
        'email',
        'first_name',
        'lastname',
        'last_name',
        'full_name',
        'name',
        'phone',
        'mobile',
        'ssn',
        'social_security_number',
        'date_of_birth',
        'dob',
        'address',
        'street',
        'zip',
        'postal_code',
        'city',
    ];

    /**
     * @param array<string, mixed> $payload
     */
    public static function enqueue(array $payload, string $optimizationType, ?int $sessionId = null, ?string $state = null): bool
    {
        global $wpdb;

        if (! isset($wpdb)) {
            return false;
        }

        $table = $wpdb->prefix . 'cp_optimization_queue';
        $now = gmdate('Y-m-d H:i:s');

        $inserted = $wpdb->insert(
            $table,
            [
                'session_id' => $sessionId,
                'state' => $state,
                'status' => 'pending',
                'optimization_type' => sanitize_key($optimizationType),
                'payload' => wp_json_encode(self::scrubPii($payload)),
                'attempts' => 0,
                'available_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s']
        );

        return $inserted !== false;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function scrubPii(array $data): array
    {
        $scrubbed = [];

        foreach ($data as $key => $value) {
            $normalizedKey = strtolower((string) $key);

            if (in_array($normalizedKey, self::PII_KEYS, true)) {
                $scrubbed[$key] = '[redacted]';
                continue;
            }

            if (is_array($value)) {
                $scrubbed[$key] = self::scrubPii($value);
                continue;
            }

            $scrubbed[$key] = $value;
        }

        return $scrubbed;
    }
}
