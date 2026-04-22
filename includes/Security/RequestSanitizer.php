<?php

namespace CaringPays\CareAdvisor\Security;

final class RequestSanitizer
{
    public static function text(mixed $value): string
    {
        return sanitize_text_field((string) $value);
    }

    public static function key(mixed $value): string
    {
        return sanitize_key((string) $value);
    }

    public static function integer(mixed $value): int
    {
        return absint($value);
    }

    public static function html(mixed $value): string
    {
        return wp_kses((string) $value, []);
    }
}
