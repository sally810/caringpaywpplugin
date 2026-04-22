<?php

namespace CaringPays\CareAdvisor\Escalation;

final class EscalationService
{
    private NotificationManager $notificationManager;

    public function __construct(?NotificationManager $notificationManager = null)
    {
        $this->notificationManager = $notificationManager ?? new NotificationManager();
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>|null
     */
    public function evaluate(string $message, array $context = []): ?array
    {
        $code = TriggerMatcher::resolveCode($message, $context);
        if ($code === null) {
            return null;
        }

        $actionMap = EscalationPolicy::actionMap();
        if (! isset($actionMap[$code])) {
            return null;
        }

        $action = $actionMap[$code];
        $event = [
            'escalation_code' => $code,
            'action' => $action['action'],
            'session_token' => $context['session_token'] ?? '',
            'session_id' => isset($context['session_id']) ? (int) $context['session_id'] : 0,
            'turn_index' => isset($context['turn_index']) ? (int) $context['turn_index'] : 0,
            'detected_at_gmt' => gmdate('c'),
            'source' => 'chat.message',
        ];

        $this->notificationManager->notify($action['channels'], $event);

        if ($code === EscalationCodes::ESC_03) {
            $this->persistEsc03EmergencyMessage();
            $this->disableAiForSession($event['session_token']);
        }

        return [
            'code' => $code,
            'action' => $action['action'],
            'bypass_ai_generation' => (bool) $action['bypass_ai_generation'],
            'channels' => $action['channels'],
            'deterministic_message' => $code === EscalationCodes::ESC_03
                ? EscalationCodes::ESC_03_EMERGENCY_MESSAGE
                : null,
        ];
    }

    private function persistEsc03EmergencyMessage(): void
    {
        update_option(
            EscalationPolicy::protectedEscalationOptionKey(),
            EscalationCodes::ESC_03_EMERGENCY_MESSAGE,
            false
        );
    }

    private function disableAiForSession(string $sessionToken): void
    {
        global $wpdb;

        if (! isset($wpdb) || $sessionToken === '') {
            return;
        }

        $sessionTable = $wpdb->prefix . 'cp_sessions';
        $wpdb->update(
            $sessionTable,
            [
                'status' => 'ai_disabled',
                'updated_at' => current_time('mysql'),
            ],
            [
                'session_uuid' => $sessionToken,
            ],
            ['%s', '%s'],
            ['%s']
        );
    }
}
