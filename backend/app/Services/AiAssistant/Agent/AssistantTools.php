<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Agent;

use stdClass;

/**
 * The tools the model may use: a closed list in QNET2's code (spec 0433, internal_contract).
 * Any other name, `confirm_write` included, is dropped before the model sees it and never
 * reaches the connector. A write is only prepared by the model: the person confirms with the button.
 */
final class AssistantTools
{
    public const string SEARCH_OPERATIONS = 'search_operations';

    public const string LIST_RECORDS = 'list_records';

    public const string RUN_READ_CODE = 'run_read_code';

    public const string PREPARE_WRITE = 'prepare_write';

    /** Fixed order: the tool definitions are part of the cached prompt prefix. */
    public const array ALLOWED = [self::SEARCH_OPERATIONS, self::LIST_RECORDS, self::RUN_READ_CODE, self::PREPARE_WRITE];

    /** Argument names that identify what a call is about, in order of preference. */
    private const array OPERATION_ARGUMENTS = ['operation_id', 'domain', 'module', 'query'];

    private const int OPERATION_MAX_CHARS = 80;

    public static function isAllowed(string $name): bool
    {
        return in_array($name, self::ALLOWED, true);
    }

    /**
     * What a call asked for, in a few words, kept on the step so later turns can name it when the
     * result is no longer sent. shortcut: first of a fixed list of argument names, not a schema read.
     */
    public static function operation(stdClass $arguments): ?string
    {
        foreach (self::OPERATION_ARGUMENTS as $name) {
            $value = $arguments->{$name} ?? null;

            if (is_string($value) && $value !== '') {
                return mb_substr($value, 0, self::OPERATION_MAX_CHARS);
            }
        }

        return null;
    }

    /**
     * Short step label shown to the person, already translated.
     */
    public static function label(string $name, string $locale): string
    {
        $text = match ($name) {
            self::SEARCH_OPERATIONS => 'Looking for the right operation',
            self::LIST_RECORDS => 'Reading the records',
            self::PREPARE_WRITE => 'Preparing the change',
            default => 'Reading and processing the data',
        };

        return __($text, [], $locale);
    }
}
