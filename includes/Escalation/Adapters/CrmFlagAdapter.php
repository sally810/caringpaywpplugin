<?php

namespace CaringPays\CareAdvisor\Escalation\Adapters;

final class CrmFlagAdapter implements NotificationAdapterInterface
{
    public function notify(array $event): void
    {
        /**
         * Integrators can flag CRM entities through this hook.
         */
        do_action('caringpays_care_advisor_escalation_notify_crm_flag', $event);
    }

    public function channel(): string
    {
        return 'crm_flag';
    }
}
