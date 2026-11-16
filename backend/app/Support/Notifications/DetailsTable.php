<?php

declare(strict_types=1);

namespace App\Support\Notifications;

/**
 * Translates the "scheda dettagli" of a notification email into the rows the
 * mail views print (direttiva utente 2026-08-04, spec 0381).
 *
 * Rows and not markup: the HTML view renders them as a table and the plain
 * text view as "Label: value" lines, each escaping in its own way, so the
 * same data reaches both without a markdown table leaking pipes into the
 * text version.
 *
 * Labels are translated HERE and not by the caller: this runs inside
 * toMail(), within the per-recipient locale Laravel switched to via
 * HasLocalePreference, so the same notification reaches an Italian user in
 * Italian and an English one in English.
 */
final class DetailsTable
{
    private const string TRANSLATABLE_VALUE_PREFIX = 'notifications.values.';

    /**
     * @param  array<string, string>  $details  i18n label key => value, ordered
     * @return array<int, array{label: string, value: string}>
     */
    public static function rows(array $details): array
    {
        $rows = [];

        foreach ($details as $label => $value) {
            $rows[] = ['label' => __($label), 'value' => self::value($value)];
        }

        return $rows;
    }

    /**
     * Most values are DATA and travel verbatim. A few are terms that must
     * themselves be translated ("Cliente"/"Fornitore"): the builder marks
     * those by passing their i18n key instead of a rendered string, so the
     * translation happens here, in the recipient's locale, and not in the
     * locale of whoever performed the write.
     */
    private static function value(string $value): string
    {
        return str_starts_with($value, self::TRANSLATABLE_VALUE_PREFIX) ? __($value) : $value;
    }
}
