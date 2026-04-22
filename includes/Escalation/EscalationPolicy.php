<?php

namespace CaringPays\CareAdvisor\Escalation;

final class EscalationPolicy
{
    /**
     * @return array<string,array<string,mixed>>
     */
    public static function actionMap(): array
    {
        return [
            EscalationCodes::ESC_01 => [
                'action' => 'route_to_human_advisor',
                'bypass_ai_generation' => false,
                'channels' => ['developer_log', 'crm_flag'],
            ],
            EscalationCodes::ESC_02 => [
                'action' => 'collect_compliance_metadata',
                'bypass_ai_generation' => false,
                'channels' => ['developer_log', 'crm_flag'],
            ],
            EscalationCodes::ESC_03 => [
                'action' => 'emergency_intervention',
                'bypass_ai_generation' => true,
                'channels' => ['developer_log', 'slack', 'sms', 'crm_flag'],
            ],
            EscalationCodes::ESC_04 => [
                'action' => 'legal_review_queue',
                'bypass_ai_generation' => true,
                'channels' => ['developer_log', 'slack', 'crm_flag'],
            ],
            EscalationCodes::ESC_05 => [
                'action' => 'consultation_workflow_route',
                'bypass_ai_generation' => true,
                'channels' => ['developer_log', 'slack', 'crm_flag'],
            ],
            EscalationCodes::ESC_06 => [
                'action' => 'privacy_incident_flow',
                'bypass_ai_generation' => true,
                'channels' => ['developer_log', 'slack', 'crm_flag'],
            ],
            EscalationCodes::ESC_07 => [
                'action' => 'manual_support_followup',
                'bypass_ai_generation' => false,
                'channels' => ['developer_log', 'crm_flag'],
            ],
        ];
    }

    public static function protectedEscalationOptionKey(): string
    {
        return 'caringpays_escalation_esc03_emergency_message';
    }

    /**
     * @return string[]
     */
    public static function protectedOptionKeysForAiEditWorkflows(): array
    {
        return [
            self::protectedEscalationOptionKey(),
        ];
    }
}
