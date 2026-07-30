<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts;

/**
 * Small, stateless shape-checking primitives shared by every
 * DocumentLayoutConfigValidator-family class (spec 0069): each method checks
 * one thing and records at most one error, at the given dotted path, into
 * the shared DocumentLayoutConfigErrorBag — never throws, never returns a
 * value the caller needs to branch on beyond "was it valid" (callers that
 * need to branch, e.g. to skip a dependent check, call `$errors->has($path)`
 * themselves).
 */
final class ConfigShapeAssertions
{
    /**
     * @param  array<string, mixed>  $value
     * @param  array<int, string>  $allowedKeys
     */
    public static function assertKnownKeys(string $path, array $value, array $allowedKeys, DocumentLayoutConfigErrorBag $errors): void
    {
        foreach (array_keys($value) as $key) {
            if (! is_string($key) || ! in_array($key, $allowedKeys, true)) {
                $errors->add("{$path}.{$key}", 'Unknown key.');
            }
        }
    }

    public static function assertIntInRange(string $path, mixed $value, int $min, int $max, DocumentLayoutConfigErrorBag $errors): void
    {
        if (! is_int($value) || $value < $min || $value > $max) {
            $errors->add($path, "Must be an integer between {$min} and {$max}.");
        }
    }

    public static function assertNullableIntInRange(string $path, mixed $value, int $min, int $max, DocumentLayoutConfigErrorBag $errors): void
    {
        if ($value === null) {
            return;
        }

        self::assertIntInRange($path, $value, $min, $max, $errors);
    }

    /**
     * @param  array<int, string>  $allowed
     */
    public static function assertEnum(string $path, mixed $value, array $allowed, DocumentLayoutConfigErrorBag $errors): void
    {
        if (! in_array($value, $allowed, true)) {
            $errors->add($path, 'Must be one of: '.implode(', ', $allowed).'.');
        }
    }

    /**
     * @param  array<int, string>  $allowed
     */
    public static function assertNullableEnum(string $path, mixed $value, array $allowed, DocumentLayoutConfigErrorBag $errors): void
    {
        if ($value === null) {
            return;
        }

        self::assertEnum($path, $value, $allowed, $errors);
    }

    public static function assertBool(string $path, mixed $value, DocumentLayoutConfigErrorBag $errors): void
    {
        if (! is_bool($value)) {
            $errors->add($path, 'Must be a boolean.');
        }
    }

    public static function assertNonEmptyString(string $path, mixed $value, DocumentLayoutConfigErrorBag $errors): void
    {
        if (! is_string($value) || $value === '') {
            $errors->add($path, 'Must be a non-empty string.');
        }
    }

    public static function assertNullableString(string $path, mixed $value, DocumentLayoutConfigErrorBag $errors): void
    {
        if ($value === null) {
            return;
        }

        if (! is_string($value)) {
            $errors->add($path, 'Must be a string.');
        }
    }

    /**
     * A 6-digit RRGGBB hex color, WITHOUT a leading `#` (OOXML convention,
     * see config_schema's units-of-measure preamble).
     */
    public static function assertHexColor(string $path, mixed $value, DocumentLayoutConfigErrorBag $errors): void
    {
        if (! is_string($value) || preg_match('/^[0-9A-Fa-f]{6}$/', $value) !== 1) {
            $errors->add($path, 'Must be a 6-digit RRGGBB hex color without a leading #.');
        }
    }

    public static function assertNullableHexColor(string $path, mixed $value, DocumentLayoutConfigErrorBag $errors): void
    {
        if ($value === null) {
            return;
        }

        self::assertHexColor($path, $value, $errors);
    }
}
