<?php

namespace App\Http\Requests\ActivityLog;

use App\DataObjects\ActivityLog\ActivityLogCursor;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Validates GET /api/activity-log/{resource}/{id} (spec 0034): `per_page`
 * (1..100, default 25), an opaque, well-formed `cursor` and the optional
 * `event` filter (allow-list, absent = every event).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller, which resolves the resource → model via ActivityLogRegistry
 * before it can check `{resource}.viewActivity`/Policy `view`).
 */
class ActivityLogIndexRequest extends FormRequest
{
    private const int DEFAULT_PER_PAGE = 25;

    private const int MAX_PER_PAGE = 100;

    /**
     * Events the timeline can be narrowed to. Allow-list, never raw input:
     * the value reaches a bound `where('event', ...)` (security.md §8).
     */
    private const array FILTERABLE_EVENTS = ['created', 'updated'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            'cursor' => ['sometimes', 'nullable', 'string'],
            'event' => ['sometimes', Rule::in(self::FILTERABLE_EVENTS)],
        ];
    }

    /**
     * A malformed (but present) cursor is a 422, not a silent "first page".
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $cursor = $this->query('cursor');

            if ($cursor === null || $cursor === '') {
                return;
            }

            try {
                ActivityLogCursor::decode($cursor);
            } catch (InvalidArgumentException) {
                $validator->errors()->add('cursor', 'The cursor is malformed.');
            }
        });
    }

    public function perPage(): int
    {
        $perPage = $this->validated('per_page');

        return $perPage === null ? self::DEFAULT_PER_PAGE : (int) $perPage;
    }

    public function cursor(): ?ActivityLogCursor
    {
        $cursor = $this->validated('cursor');

        return $cursor === null ? null : ActivityLogCursor::decode($cursor);
    }

    /**
     * The event the timeline is narrowed to, or null for every event.
     */
    public function event(): ?string
    {
        $event = $this->validated('event');

        return $event === null ? null : (string) $event;
    }
}
