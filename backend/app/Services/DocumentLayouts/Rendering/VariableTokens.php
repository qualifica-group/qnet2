<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts\Rendering;

use Closure;

/**
 * The `{category.key}` token syntax shared by every render subject: both parts
 * are snake_case identifiers, split on the FIRST dot only (`quote_attributes`
 * is itself an underscored category name, not a nested path).
 */
final class VariableTokens
{
    private const string TOKEN_PATTERN = '/\{([a-zA-Z][a-zA-Z0-9_]*)\.([a-zA-Z0-9_]+)\}/';

    /**
     * Replace every token in $text with $resolve($category, $key); plain text
     * around the tokens is untouched.
     *
     * @param  Closure(string, string): string  $resolve
     */
    public static function replace(string $text, Closure $resolve): string
    {
        return (string) preg_replace_callback(
            self::TOKEN_PATTERN,
            static fn (array $matches): string => $resolve($matches[1], $matches[2]),
            $text,
        );
    }
}
