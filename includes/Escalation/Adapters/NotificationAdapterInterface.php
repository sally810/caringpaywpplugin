<?php

namespace CaringPays\CareAdvisor\Escalation\Adapters;

interface NotificationAdapterInterface
{
    /**
     * @param array<string,mixed> $event
     */
    public function notify(array $event): void;

    public function channel(): string;
}
