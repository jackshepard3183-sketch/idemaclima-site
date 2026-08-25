<?php

declare(strict_types=1);

namespace App\Core;

final class Validator
{
    public static function int(mixed $value, int $default = 0): int
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false ? (int) $value : $default;
    }

    public static function bool(mixed $value): int
    {
        return in_array($value, [1, '1', true, 'on', 'yes'], true) ? 1 : 0;
    }

    public static function requiredString(mixed $value, string $label, int $max, array &$errors): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            $errors[] = "$label è obbligatorio.";
        } elseif (mb_strlen($value) > $max) {
            $errors[] = "$label supera $max caratteri.";
        }
        return $value;
    }

    public static function optionalString(mixed $value, int $max, string $label, array &$errors): ?string
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        if (mb_strlen($value) > $max) $errors[] = "$label supera $max caratteri.";
        return $value;
    }

    public static function slug(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($ascii !== false) $value = $ascii;
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        return trim($value, '-');
    }
}
