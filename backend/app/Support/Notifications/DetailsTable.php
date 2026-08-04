<?php

declare(strict_types=1);

namespace App\Support\Notifications;

use Illuminate\Support\HtmlString;

/**
 * Renders the "scheda dettagli" of a notification email as a MARKDOWN table
 * (direttiva utente 2026-08-04).
 *
 * Markdown and not HTML because `Illuminate\Mail\Markdown` parses the body
 * with `html_input => 'escape'`: raw HTML injected into a MailMessage line
 * comes out as visible tags. The same parser enables CommonMark's
 * TableExtension, so the pipe syntax below renders a real `<table>`, which
 * the mail theme then styles (`.content-cell table`).
 *
 * Labels are translated HERE and not by the caller: `__()` runs at render
 * time, inside the per-recipient locale Laravel switched to via
 * HasLocalePreference, so the same notification reaches an Italian user in
 * Italian and an English one in English.
 */
final class DetailsTable
{
    private const string TRANSLATABLE_VALUE_PREFIX = 'notifications.values.';

    /**
     * Returns an HtmlString, NOT a plain string, and that is load-bearing:
     * `SimpleMessage::formatLine()` collapses every newline of a plain string
     * into a space, which would flatten this table onto one line and print
     * the pipes verbatim. An Htmlable is passed through untouched — and
     * Blade's `e()` leaves it unescaped — so the parser downstream sees a
     * real markdown table.
     *
     * @param  array<string, string>  $details  i18n label key => value, ordered
     * @return HtmlString|null null when there is nothing to show, so the
     *                         caller omits the block instead of printing an
     *                         empty table
     */
    public static function markdown(array $details): ?HtmlString
    {
        if ($details === []) {
            return null;
        }

        $rows = [
            '| '.__('notifications.table.field').' | '.__('notifications.table.value').' |',
            '| :--- | :--- |',
        ];

        foreach ($details as $label => $value) {
            $rows[] = '| '.self::cell(__($label)).' | '.self::cell(self::value($value)).' |';
        }

        return new HtmlString(implode("\n", $rows));
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

    /**
     * A pipe inside a value would close the cell early and shift every column
     * after it; a newline would end the table mid-row.
     */
    private static function cell(string $value): string
    {
        return str_replace(['|', "\r\n", "\n", "\r"], ['\\|', ' ', ' ', ' '], $value);
    }
}
