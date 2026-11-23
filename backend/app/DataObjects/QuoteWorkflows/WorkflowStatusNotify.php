<?php

declare(strict_types=1);

namespace App\DataObjects\QuoteWorkflows;

use Closure;
use Illuminate\Validation\Rule;

/**
 * The email a working status sends when a user moves an offer into it (spec
 * 0419): subject and plain-text message written in the configurator, and the
 * recipients, combinable like the table row events (spec 0390 D-3). Shared by
 * the three status write endpoints (workflow create/update, default set).
 */
final class WorkflowStatusNotify
{
    public const int SUBJECT_MAX = 191;

    public const int MESSAGE_MAX = 5000;

    public const int MAX_IDS = 50;

    public const int MAX_EMAILS = 20;

    private const array RECIPIENT_LISTS = ['user_ids', 'role_ids', 'emails'];

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'statuses.*.notify' => ['sometimes', 'nullable', 'array', self::hasRecipient(...)],
            'statuses.*.notify.subject' => ['required_with:statuses.*.notify', 'string', 'max:'.self::SUBJECT_MAX],
            'statuses.*.notify.message' => ['required_with:statuses.*.notify', 'string', 'max:'.self::MESSAGE_MAX],
            'statuses.*.notify.record_team' => ['sometimes', 'boolean'],
            // `distinct` on a nested wildcard would compare the lists of every status: checked per list instead.
            'statuses.*.notify.user_ids' => ['sometimes', 'array', 'max:'.self::MAX_IDS, self::distinctValues(...)],
            'statuses.*.notify.user_ids.*' => ['integer', Rule::exists('users', 'id')],
            'statuses.*.notify.role_ids' => ['sometimes', 'array', 'max:'.self::MAX_IDS, self::distinctValues(...)],
            'statuses.*.notify.role_ids.*' => ['integer', Rule::exists('roles', 'id')],
            'statuses.*.notify.emails' => ['sometimes', 'array', 'max:'.self::MAX_EMAILS, self::distinctValues(...)],
            'statuses.*.notify.emails.*' => ['string', 'email'],
        ];
    }

    /**
     * The validated `notify` of one submitted status, normalized; null when
     * absent (a full row sync: no notify sent = no notification, like
     * `requires_note`).
     *
     * @param  array<string, mixed>  $status
     * @return array{subject: string, message: string, record_team: bool, user_ids: array<int, int>, role_ids: array<int, int>, emails: array<int, string>}|null
     */
    public static function fromPayload(array $status): ?array
    {
        $notify = $status['notify'] ?? null;

        if (! is_array($notify)) {
            return null;
        }

        return [
            'subject' => trim((string) $notify['subject']),
            'message' => trim((string) $notify['message']),
            'record_team' => (bool) ($notify['record_team'] ?? false),
            'user_ids' => array_map('intval', $notify['user_ids'] ?? []),
            'role_ids' => array_map('intval', $notify['role_ids'] ?? []),
            'emails' => array_values(array_map('strval', $notify['emails'] ?? [])),
        ];
    }

    private static function distinctValues(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        $normalized = array_map(fn (mixed $item): string => mb_strtolower((string) (is_scalar($item) ? $item : '')), $value);

        if (count($normalized) !== count(array_unique($normalized))) {
            $fail(__('The list contains duplicate values.'));
        }
    }

    private static function hasRecipient(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || ($value['record_team'] ?? false) === true) {
            return;
        }

        foreach (self::RECIPIENT_LISTS as $list) {
            if (is_array($value[$list] ?? null) && $value[$list] !== []) {
                return;
            }
        }

        $fail(__('The notification needs at least one recipient.'));
    }
}
