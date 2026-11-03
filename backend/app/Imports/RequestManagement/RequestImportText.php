<?php

namespace App\Imports\RequestManagement;

use Illuminate\Support\Str;

/**
 * Comparison keys for names typed by hand in a file: case, accents and
 * spacing never make two spellings different (spec 0348).
 */
final class RequestImportText
{
    /**
     * "Zhour EL  HAJIRI" and "zhour el hajiri" share one key.
     */
    public static function key(string $value): string
    {
        // Excel stores a line break typed in a cell as the literal "_x000D_".
        $value = str_ireplace('_x000D_', ' ', $value);

        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', Str::ascii($value))));
    }

    /**
     * Lower-case ASCII words of the value, punctuation dropped.
     *
     * @return array<int, string>
     */
    public static function words(string $value): array
    {
        $clean = (string) preg_replace('/[^a-z0-9]+/', ' ', self::key($value));

        return array_values(array_filter(explode(' ', trim($clean)), static fn (string $word): bool => $word !== ''));
    }
}
