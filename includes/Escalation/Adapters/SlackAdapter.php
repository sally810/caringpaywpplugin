<?php

namespace CaringPays\CareAdvisor\Escalation\Adapters;

final class SlackAdapter implements NotificationAdapterInterface
{
    public function notify(array $event): void
    {
        /**
         * Integrators can dispatch Slack notifications through this hook.
         */
        do_action('caringpays_care_advisor_escalation_notify_slack', $event);
    }

    public function channel(): string
    {
        return 'slack';
    }
}
