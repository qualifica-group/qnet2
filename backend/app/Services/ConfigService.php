<?php

namespace App\Services;

use App\Enums\LocaleEnum;

/**
 * Assembles the payload for the PUBLIC bootstrap endpoint GET /api/config.
 *
 * The data served here is non-sensitive presentation metadata the frontend
 * needs before authentication (e.g. enum options for selects/badges on the
 * login and public forms). The exposable surface is a fixed server-side
 * allowlist (config/config.php) — never derived from request input — so no
 * arbitrary class can be reflected from the outside. Alongside the enums it
 * carries the localization defaults (config/geo.php), which tell the client
 * whether the app runs in national or international mode.
 *
 * Locale is resolved per-request from the (restricted) Accept-Language header
 * before the enums are read, so case labels are translated for the caller even
 * though there is no authenticated user yet.
 */
class ConfigService
{
    /**
     * Build the public bootstrap payload.
     *
     * `data` is an extensible object: future non-sensitive sections live
     * alongside `enums`. This is the terminal serialized payload (the API
     * contract), not an internal DTO, so a plain array is the right shape here.
     *
     * @return array{enums: array<string, array<int, array{value: string, label: string, color: string|null, icon: string|null, is_default: bool, hidden_on_form: bool}>>, localization: array{default_country_iso2: string|null}}
     */
    public function bootstrap(?string $acceptLanguage): array
    {
        app()->setLocale($this->resolveLocale($acceptLanguage));

        return [
            'enums' => $this->enums(),
            'localization' => $this->localization(),
        ];
    }

    /**
     * Localization defaults driving national vs international mode.
     *
     * `default_country_iso2` is the country code the client preselects on an
     * empty geo cascade, or null in international mode (config/geo.php ←
     * DEFAULT_COUNTRY_ISO2). Normalized to uppercase so the client can match it
     * against the country list without caring how the env was written.
     *
     * SECURITY: a static ISO 3166-1 alpha-2 code from server config — not
     * user-, tenant- or permission-scoped, and not derived from request input —
     * so it is safe on this PUBLIC endpoint.
     *
     * @return array{default_country_iso2: string|null}
     */
    private function localization(): array
    {
        $iso2 = strtoupper(trim((string) config('geo.default_country_iso2', '')));

        return [
            'default_country_iso2' => $iso2 === '' ? null : $iso2,
        ];
    }

    /**
     * Serialize every allowlisted form enum, dropping the cases flagged
     * #[HiddenOnForm(true)] (the payload feeds selects/forms).
     *
     * @return array<string, array<int, array{value: string, label: string, color: string|null, icon: string|null, is_default: bool, hidden_on_form: bool}>>
     */
    private function enums(): array
    {
        /** @var array<string, class-string> $allowlist */
        $allowlist = config('config.form_enums', []);

        $enums = [];

        foreach ($allowlist as $key => $class) {
            $enums[$key] = array_values(array_map(
                static fn ($meta): array => $meta->toArray(),
                array_filter(
                    $class::options(),
                    static fn ($meta): bool => ! $meta->hiddenOnForm,
                ),
            ));
        }

        return $enums;
    }

    /**
     * Resolve the request locale from the Accept-Language header, restricted to
     * the supported locales. The header is parsed leniently (only the primary
     * subtag of each entry is considered) and validated against the allowlist;
     * anything unsupported or malformed falls back to config('app.locale').
     */
    private function resolveLocale(?string $acceptLanguage): string
    {
        $fallback = (string) config('app.locale', 'en');
        $supported = LocaleEnum::values();

        if ($acceptLanguage === null || $acceptLanguage === '') {
            return $fallback;
        }

        foreach (explode(',', $acceptLanguage) as $part) {
            // Drop the q-weight ("it;q=0.8" → "it") and region ("en-US" → "en").
            $primary = strtolower(trim(explode(';', $part)[0]));
            $primary = explode('-', $primary)[0];

            if (in_array($primary, $supported, true)) {
                return $primary;
            }
        }

        return $fallback;
    }
}
