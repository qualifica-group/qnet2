<?php

namespace App\Imports\Recognition;

use App\Imports\ImportRowContext;
use App\Support\InputFormat;
use Illuminate\Support\Str;
use Normalizer;

/**
 * Cleans the person-name fields of an imported row down to ordinary
 * characters (spec 0138): lead exports copy the social-profile display name,
 * so `𝓜𝓮𝓻𝔂.`, a trailing heart symbol or `Martina_Lipari` reach the file
 * as-is. Runs BEFORE NameSplitRecognizer, so the split works on the cleaned
 * `full_name`.
 *
 * Accented Latin letters are legitimate (`Macrì`, `Fogaça`) and kept. The
 * cleaned name then takes the same canonical casing the card form stores
 * (`InputFormat::personName`): a casing-only change is not flagged, a
 * removed or replaced character is. The formatted value lands in the row's
 * mapped values, so a later review edit replays this recognizer on an
 * already clean value and never re-flags it.
 *
 * `Normalizer` comes from `ext-intl` or, without it, from
 * symfony/polyfill-intl-normalizer (a framework dependency); `Transliterator`
 * has no polyfill, hence `Str::ascii` for non-Latin letters.
 */
final class PersonNameRecognizer implements RowRecognizer
{
    /** @var array<int, string> */
    private const array NAME_FIELDS = [
        NameSplitRecognizer::FULL_NAME_FIELD,
        NameSplitRecognizer::FIRST_NAME_FIELD,
        NameSplitRecognizer::LAST_NAME_FIELD,
    ];

    /**
     * Any character outside the kept set: ASCII letters, accented Latin
     * letters (Latin-1 Supplement, Extended-A/B, Extended Additional — the
     * letter lookahead leaves out `×`/`÷`), whitespace, apostrophes, hyphen.
     * Phonetic small capitals (`ᴀ`) are Latin script but not ordinary
     * letters, so they fall outside the ranges on purpose.
     */
    private const string NOT_ORDINARY_CHARACTER = '/(?!(?=\p{L})[\x{00C0}-\x{024F}\x{1E00}-\x{1EFF}])[^A-Za-z\s\'\x{2019}-]/u';

    public function recognize(ImportRowContext $context, array $mapped): RecognitionResult
    {
        $resolved = [];
        $messages = [];

        foreach (self::NAME_FIELDS as $field) {
            $original = $mapped[$field] ?? null;

            if (! is_string($original) || trim($original) === '') {
                continue;
            }

            $cleaned = self::clean($original);
            $formatted = InputFormat::personName($cleaned);

            if ($formatted === $original) {
                continue;
            }

            $resolved[$field] = $formatted;

            // Whitespace and casing alone are not worth a review.
            if ($cleaned !== self::collapseWhitespace($original)) {
                $messages[] = "{$field} contained special characters and was cleaned to \"{$formatted}\"; review it.";
            }
        }

        return $resolved === [] ? RecognitionResult::none() : RecognitionResult::resolved($resolved, $messages !== [], $messages);
    }

    /**
     * `𝓜𝓮𝓻𝔂.` -> `Mery`, `Martina_Lipari` -> `Martina Lipari`, `Марио` -> `Mario`.
     */
    public static function clean(string $value): string
    {
        // Step 1: compatibility forms (styled, full-width letters) to their plain letter.
        $normalized = Normalizer::normalize($value, Normalizer::FORM_KC);

        // Step 2: transliterate or drop every character outside the kept set.
        $replaced = (string) preg_replace_callback(
            self::NOT_ORDINARY_CHARACTER,
            static fn (array $match): string => self::replaceCharacter($match[0]),
            $normalized === false ? $value : $normalized,
        );

        return self::collapseWhitespace($replaced);
    }

    /**
     * A foreign letter becomes its ASCII transliteration (possibly empty);
     * punctuation separates words (`Mery_Angy`, `J.L.`), so it becomes a
     * space; digits, emoji and symbols are removed.
     */
    private static function replaceCharacter(string $character): string
    {
        if (preg_match('/\p{L}/u', $character) === 1) {
            return (string) preg_replace('/[^A-Za-z]/', '', Str::ascii($character));
        }

        return preg_match('/[\p{P}\p{Sm}]/u', $character) === 1 ? ' ' : '';
    }

    private static function collapseWhitespace(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }
}
