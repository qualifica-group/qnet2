<?php

namespace App\Migrations\Support;

use Illuminate\Support\Str;

/**
 * Re-splits a legacy private customer's name into first name and surname
 * using the Italian tax code (spec 0189, G-11). The legacy `companies.name`
 * holds both in mixed order and its `separaNomeCognome` heuristic often
 * inverts them; the first six tax-code letters encode surname + first name,
 * so the split whose codes match them is the right one.
 *
 * Every split point is tried in both orders. A full six-letter match wins;
 * otherwise a split matching only the surname code is taken when it is the
 * only one (the first name may be abbreviated or spelled differently in the
 * legacy). No match leaves the legacy split untouched.
 */
final class PersonNameSplitter
{
    private const string PERSONAL_TAX_CODE = '/^[A-Z]{6}\d{2}[A-Z]\d{2}[A-Z]\d{3}[A-Z]$/';

    private const int CODE_LENGTH = 3;

    /** A first name with at least this many consonants is coded on the 1st, 3rd and 4th. */
    private const int FIRST_NAME_SKIP_THRESHOLD = 4;

    private const string VOWELS = 'AEIOU';

    private const string PADDING = 'XXX';

    /**
     * @return array{0: ?string, 1: ?string} [first name, surname]
     */
    public function split(?string $firstName, ?string $lastName, ?string $taxCode): array
    {
        $taxCode = strtoupper(trim((string) $taxCode));
        $words = preg_split('/\s+/', trim($firstName.' '.$lastName), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (! preg_match(self::PERSONAL_TAX_CODE, $taxCode) || count($words) < 2) {
            return [$firstName, $lastName];
        }

        // Step 1: the legacy split goes first, so a correct one is never reshuffled.
        $candidates = [[(string) $firstName, (string) $lastName], ...$this->candidates($words)];
        $surnameCode = substr($taxCode, 0, self::CODE_LENGTH);
        $fullCode = substr($taxCode, 0, self::CODE_LENGTH * 2);

        // Step 2: surname and first name codes both match.
        foreach ($candidates as [$first, $last]) {
            if ($this->surnameCode($last).$this->firstNameCode($first) === $fullCode) {
                return [$first, $last];
            }
        }

        // Step 3: only the surname code matches, and on a single split.
        $surnameMatches = array_values(array_unique(array_filter(
            $candidates,
            fn (array $candidate): bool => $this->surnameCode($candidate[1]) === $surnameCode,
        ), SORT_REGULAR));

        return count($surnameMatches) === 1 ? $surnameMatches[0] : [$firstName, $lastName];
    }

    /**
     * Every split point of the words, in both orders, as [first name, surname].
     *
     * @param  array<int, string>  $words
     * @return array<int, array{0: string, 1: string}>
     */
    private function candidates(array $words): array
    {
        $candidates = [];

        for ($k = 1; $k < count($words); $k++) {
            $head = implode(' ', array_slice($words, 0, $k));
            $tail = implode(' ', array_slice($words, $k));
            $candidates[] = [$tail, $head];
            $candidates[] = [$head, $tail];
        }

        return $candidates;
    }

    private function surnameCode(string $surname): string
    {
        [$consonants, $vowels] = $this->letters($surname);

        return substr($consonants.$vowels.self::PADDING, 0, self::CODE_LENGTH);
    }

    private function firstNameCode(string $firstName): string
    {
        [$consonants, $vowels] = $this->letters($firstName);

        if (strlen($consonants) >= self::FIRST_NAME_SKIP_THRESHOLD) {
            return $consonants[0].$consonants[2].$consonants[3];
        }

        return substr($consonants.$vowels.self::PADDING, 0, self::CODE_LENGTH);
    }

    /**
     * @return array{0: string, 1: string} [consonants, vowels], uppercase ASCII letters only
     */
    private function letters(string $name): array
    {
        $letters = (string) preg_replace('/[^A-Z]/', '', strtoupper(Str::ascii($name)));
        $consonants = '';
        $vowels = '';

        foreach (str_split($letters) as $letter) {
            str_contains(self::VOWELS, $letter) ? $vowels .= $letter : $consonants .= $letter;
        }

        return [$consonants, $vowels];
    }
}
