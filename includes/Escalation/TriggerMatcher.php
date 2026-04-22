<?php

namespace CaringPays\CareAdvisor\Escalation;

use CaringPays\CareAdvisor\Security\RequestSanitizer;

final class TriggerMatcher
{
    /**
     * @param array<string,mixed> $context
     */
    public static function resolveCode(string $message, array $context = []): ?string
    {
        $explicitCode = RequestSanitizer::text($context['escalation_code'] ?? '');
        if ($explicitCode !== '' && isset(EscalationPolicy::actionMap()[$explicitCode])) {
            return $explicitCode;
        }

        $normalizedMessage = strtolower(wp_strip_all_tags($message));
        $flags = self::normalizeFlags($context['flags'] ?? []);

        if (self::containsAny($normalizedMessage, ['suicide', 'kill myself', 'overdose', 'harm myself']) || in_array('emergency', $flags, true)) {
            return EscalationCodes::ESC_03;
        }

        if (self::containsAny($normalizedMessage, ['lawyer', 'attorney', 'legal notice'])) {
            return EscalationCodes::ESC_04;
        }

        if (self::containsAny($normalizedMessage, ['fraud', 'stolen', 'identity theft'])) {
            return EscalationCodes::ESC_05;
        }

        if (self::containsAny($normalizedMessage, ['privacy breach', 'hipaa', 'data leak'])) {
            return EscalationCodes::ESC_06;
        }

        if (self::containsAny($normalizedMessage, ['agent', 'human', 'representative'])) {
            return EscalationCodes::ESC_01;
        }

        if (in_array('compliance', $flags, true)) {
            return EscalationCodes::ESC_02;
        }

        if (in_array('follow_up', $flags, true)) {
            return EscalationCodes::ESC_07;
        }

        return null;
    }

    private static function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param mixed $flagsRaw
     * @return string[]
     */
    private static function normalizeFlags(mixed $flagsRaw): array
    {
        if (! is_array($flagsRaw)) {
            return [];
        }

        $flags = [];
        foreach ($flagsRaw as $flag) {
            $normalized = RequestSanitizer::key($flag);
            if ($normalized === '') {
                continue;
            }

            $flags[] = $normalized;
        }

        return array_values(array_unique($flags));
    }
}
