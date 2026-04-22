<?php

namespace CaringPays\CareAdvisor\Escalation;

use CaringPays\CareAdvisor\Escalation\Adapters\CrmFlagAdapter;
use CaringPays\CareAdvisor\Escalation\Adapters\DeveloperLogAdapter;
use CaringPays\CareAdvisor\Escalation\Adapters\NotificationAdapterInterface;
use CaringPays\CareAdvisor\Escalation\Adapters\SlackAdapter;
use CaringPays\CareAdvisor\Escalation\Adapters\SmsAdapter;

final class NotificationManager
{
    /**
     * @var array<string,NotificationAdapterInterface>
     */
    private array $adapters;

    public function __construct()
    {
        $this->adapters = [
            'developer_log' => new DeveloperLogAdapter(),
            'slack' => new SlackAdapter(),
            'sms' => new SmsAdapter(),
            'crm_flag' => new CrmFlagAdapter(),
        ];
    }

    /**
     * @param string[] $channels
     * @param array<string,mixed> $event
     */
    public function notify(array $channels, array $event): void
    {
        foreach ($channels as $channel) {
            if (! isset($this->adapters[$channel])) {
                continue;
            }

            $this->adapters[$channel]->notify($event);
        }
    }
}
