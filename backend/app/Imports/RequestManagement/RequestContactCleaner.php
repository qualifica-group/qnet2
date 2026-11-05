<?php

namespace App\Imports\RequestManagement;

use App\Enums\ContactTypeEnum;
use Illuminate\Support\Facades\Validator;

/**
 * Cleans the two contact cells of a request-import row (spec 0348, AC-010):
 * a phone cell may carry several numbers ("335... / 081..."), the first valid
 * one is the principal; a value that is not a valid phone or email is not
 * stored and is reported back so the caller can warn and keep it in the notes.
 */
final class RequestContactCleaner
{
    /** Separators between numbers in one cell: "/", "\", ";" and a spaced dash. */
    private const string PHONE_SEPARATORS = '#\s*(?:[/\\\;]|\s-\s)\s*#u';

    /**
     * @return array{phone: ?string, extra_phones: array<int, string>, rejected: array<int, string>}
     */
    public function phones(string $cell): array
    {
        $valid = [];
        $rejected = [];

        foreach (preg_split(self::PHONE_SEPARATORS, trim($cell)) ?: [] as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            if ($this->isValid(ContactTypeEnum::Phone, $part)) {
                $valid[$part] = $part;
            } else {
                $rejected[] = $part;
            }
        }

        $valid = array_values($valid);

        return [
            'phone' => $valid[0] ?? null,
            'extra_phones' => array_slice($valid, 1),
            'rejected' => $rejected,
        ];
    }

    public function email(string $cell): ?string
    {
        $email = trim($cell);

        return $email !== '' && $this->isValid(ContactTypeEnum::Email, $email) ? $email : null;
    }

    private function isValid(ContactTypeEnum $type, string $value): bool
    {
        return Validator::make(['value' => $value], ['value' => $type->valueRules()])->passes();
    }
}
