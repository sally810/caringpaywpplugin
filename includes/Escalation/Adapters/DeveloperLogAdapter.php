<?php

namespace CaringPays\CareAdvisor\Escalation\Adapters;

final class DeveloperLogAdapter implements NotificationAdapterInterface
{
    public function notify(array $event): void
    {
        $payload = wp_json_encode($event);
        if (! is_string($payload) || $payload === '') {
            $payload = '{}';
        }

        error_log('[CaringPays Escalation] ' . $payload);
    }

    public function channel(): string
    {
        return 'developer_log';
    }
}
