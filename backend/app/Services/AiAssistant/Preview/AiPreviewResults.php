<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Preview;

/**
 * The results QNET2 itself hands to the model in place of the connector (spec 0433, `fixed_texts`):
 * same envelope as the connector, a fixed code, a fixed text in the language of the turn, no data.
 */
final class AiPreviewResults
{
    public const string SKIPPED_CODE = 'non_eseguita';

    private const string SKIPPED_TEXT = 'This call was not run: a write is waiting for the confirmation of the person.';

    public static function expired(string $locale): string
    {
        return self::refusal('anteprima_scaduta', 'The preview expired before it was confirmed. Nothing was written.', $locale);
    }

    public static function cancelled(string $locale): string
    {
        return self::refusal('anteprima_annullata', 'You cancelled the preview. Nothing was written.', $locale);
    }

    public static function busy(string $locale): string
    {
        return self::refusal('anteprima_in_attesa', 'Another preview is already waiting for your confirmation.', $locale);
    }

    public static function skipped(): string
    {
        return self::refusal(self::SKIPPED_CODE, self::SKIPPED_TEXT, 'en');
    }

    private static function refusal(string $code, string $text, string $locale): string
    {
        return json_encode([
            'connettore' => ['esito' => 'fallita', 'codice' => $code, 'testo' => __($text, [], $locale)],
            'dati' => null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
