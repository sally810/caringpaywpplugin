<?php

namespace CaringPays\CareAdvisor\Escalation;

final class EscalationCodes
{
    public const ESC_01 = 'ESC-01';
    public const ESC_02 = 'ESC-02';
    public const ESC_03 = 'ESC-03';
    public const ESC_04 = 'ESC-04';
    public const ESC_05 = 'ESC-05';
    public const ESC_06 = 'ESC-06';
    public const ESC_07 = 'ESC-07';

    /**
     * Exact deterministic emergency message required for ESC-03 handling.
     */
    public const ESC_03_EMERGENCY_MESSAGE = 'If you are in immediate danger or having a medical emergency, call 911 now. If you are having thoughts of harming yourself, call or text 988 (Suicide & Crisis Lifeline) immediately.';
}
