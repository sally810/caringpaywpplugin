<?php

namespace CaringPays\CareAdvisor\Escalation\Adapters;

final class SmsAdapter implements NotificationAdapterInterface
{
    public function notify(array $event): void
    {
        /**
         * Integrators can dispatch SMS notifications through this hook.
         */
        do_action('caringpays_care_advisor_escalation_notify_sms', $event);
    }

    public function channel(): string
    {
        return 'sms';
    }
}
