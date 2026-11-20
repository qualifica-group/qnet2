<?php

declare(strict_types=1);

namespace App\Support\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Composes the body of every notification email about a record (spec 0381):
 * the sentence, the detail card, the button that opens the record's module.
 * One place, so all of them look and read the same.
 *
 * The views extend `emails.layout`, the same card the welcome and reset
 * emails use (D-8), and print the one button after the card (U-2 rev.).
 */
final class RecordMail
{
    private const string HTML_VIEW = 'emails.notification';

    private const string TEXT_VIEW = 'emails.notification-plain';

    private const string BODY_HTML_VIEW = 'emails.notification-body';

    private const string BODY_TEXT_VIEW = 'emails.notification-body-plain';

    /**
     * Shown only to a recipient who can open the record (D-4): an anagrafica
     * can be a natural person, whose tax code is personal data.
     *
     * @var array<int, string>
     */
    private const array RESTRICTED_FIELDS = [
        'notifications.fields.vat_number',
        'notifications.fields.tax_code',
        'notifications.fields.sdi_code',
    ];

    /**
     * @param  string|array<int, string>  $lead  the sentence, already translated; several
     *                                           paragraphs when the email quotes a text of its own
     * @param  array<string, string>  $details  i18n label key => value, ordered
     * @param  ?string  $path  internal SPA path, null when the recipient may
     *                         not open the record: no button then, since a
     *                         CTA that lands on a 403 is worse than none
     */
    public static function compose(MailMessage $mail, string|array $lead, array $details, ?string $path): MailMessage
    {
        return self::build($mail, $lead, $details, $path, [self::HTML_VIEW, self::TEXT_VIEW], []);
    }

    /**
     * The same email opened by a text the administrator chose (spec 0390): an
     * "Modello email" body, ALREADY rendered and sanitized, printed raw before
     * the sentence, the detail card and the button.
     *
     * @param  string|array<int, string>  $lead
     * @param  array<string, string>  $details
     */
    public static function composeWithBody(MailMessage $mail, string $htmlBody, string|array $lead, array $details, ?string $path): MailMessage
    {
        return self::build($mail, $lead, $details, $path, [self::BODY_HTML_VIEW, self::BODY_TEXT_VIEW], [
            'body' => $htmlBody,
            'plainBody' => trim(html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />'], "\n", $htmlBody)))),
        ]);
    }

    /**
     * @param  string|array<int, string>  $lead
     * @param  array<string, string>  $details
     * @param  array{0: string, 1: string}  $views
     * @param  array<string, string>  $extra
     */
    private static function build(MailMessage $mail, string|array $lead, array $details, ?string $path, array $views, array $extra): MailMessage
    {
        // Step 1: the sentence
        foreach ((array) $lead as $line) {
            $mail->line($line);
        }

        // Step 2: the button, named after the module it opens
        if ($path !== null) {
            $mail->action(__(ModuleActionLabel::for($path)), rtrim((string) config('app.frontend_url'), '/').$path);
        }

        // Step 3: the detail card, without restricted fields for a recipient
        // who cannot open the record
        $visible = $path === null ? array_diff_key($details, array_flip(self::RESTRICTED_FIELDS)) : $details;

        return $mail->view($views, [
            'appName' => config('app.name'),
            'details' => DetailsTable::rows($visible),
            ...$extra,
        ]);
    }
}
