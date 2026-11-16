<?php

declare(strict_types=1);

namespace App\DataObjects\QuoteWorkflows;

use App\Enums\WorkflowStatusGroup;
use Illuminate\Validation\Rule;

/**
 * How a submitted working status names its classification (spec 0404 part
 * A): `quote_system_status_id` links the system status and wins; `group`
 * alone is still accepted for the clients and imports that predate the
 * link, and resolves to the first system status with that behavior
 * (QuoteWorkflowStatus saving hook). One of the two is required.
 */
final class WorkflowStatusLink
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'statuses.*.quote_system_status_id' => ['nullable', 'integer', Rule::exists('quote_system_statuses', 'id')],
            'statuses.*.group' => ['required_without:statuses.*.quote_system_status_id', 'nullable', Rule::enum(WorkflowStatusGroup::class)],
        ];
    }

    /**
     * @param  array<string, mixed>  $status
     * @return array{group: ?string, quote_system_status_id: ?int}
     */
    public static function fromPayload(array $status): array
    {
        $systemStatusId = isset($status['quote_system_status_id']) ? (int) $status['quote_system_status_id'] : null;

        return [
            'group' => $systemStatusId === null && isset($status['group']) ? (string) $status['group'] : null,
            'quote_system_status_id' => $systemStatusId,
        ];
    }
}
