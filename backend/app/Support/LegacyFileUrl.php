<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The SSRF boundary of the link-only legacy documents (spec 0295 D-11): a
 * legacy file URL is usable only when it is https/http under the configured
 * `migrations.legacy_files_base_url`. Checked on import and before every fetch.
 */
final class LegacyFileUrl
{
    public static function isAllowed(?string $url): bool
    {
        $base = rtrim((string) config('migrations.legacy_files_base_url'), '/');

        if ($base === '' || $url === null || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        return in_array($scheme, ['https', 'http'], true) && str_starts_with($url, $base.'/');
    }
}
