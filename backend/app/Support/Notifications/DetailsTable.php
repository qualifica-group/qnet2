<?php

declare(strict_types=1);

namespace App\Support\Notifications;

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
    /**
     * @param  array<string, string>  $details  english label => value, ordered
     * @return string|null null when there is nothing to show, so the caller
     *                     omits the block instead of printing an empty table
     */
    public static function markdown(array $details): ?string
    {
        if ($details === []) {
            return null;
        }

        $rows = [
            '| '.__('Field').' | '.__('Value').' |',
            '| :--- | :--- |',
        ];

        foreach ($details as $label => $value) {
            $rows[] = '| '.self::cell(__($label)).' | '.self::cell($value).' |';
        }

        return implode("\n", $rows);
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
