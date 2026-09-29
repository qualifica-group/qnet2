<?php

declare(strict_types=1);

namespace App\Services\OutboundEmails;

use App\Models\User;
use App\Models\WorkOrder;
use App\Services\DocumentLayouts\Rendering\VariableResolver;

/**
 * Resolves every `{category.key}` reference in an email template's subject
 * and body against ONE Commessa + the acting sender (spec 0175, D-4), for
 * `POST /api/work-orders/{workOrder}/emails/render-template` (BE-05).
 *
 * `work_order`/`sender` are resolved directly here; every other category
 * WorkOrderEmailVariableCatalog offers (quote, client, opportunity,
 * referent, commercial, reporter, supervisor, company, company_site) is
 * delegated token-by-token to the EXISTING
 * App\Services\DocumentLayouts\Rendering\VariableResolver against
 * `$workOrder->quote` — never a second implementation of those lookups. A
 * category OUTSIDE the catalogue's allow-list (e.g. `totals`, `document`)
 * resolves to an empty string even though VariableResolver itself knows it,
 * because it is never delegated.
 *
 * The result is resolved ONCE, when the composer picks a template (D-4): the
 * caller persists the returned plain text on the draft OutboundEmail, which
 * is never ririsolto.
 */
final class WorkOrderEmailVariableResolver
{
    private const string TOKEN_PATTERN = '/\{([a-zA-Z][a-zA-Z0-9_]*)\.([a-zA-Z0-9_]+)\}/';

    /** @var array<int, string> */
    private const array DELEGATED_CATEGORIES = [
        'quote', 'client', 'opportunity', 'referent', 'commercial', 'reporter', 'supervisor', 'company', 'company_site',
    ];

    public function __construct(private readonly VariableResolver $quoteResolver) {}

    /**
     * @return array{subject: string, body: string}
     */
    public function render(string $subject, string $body, WorkOrder $workOrder, User $actor): array
    {
        return [
            'subject' => $this->substitute($subject, $workOrder, $actor, escapeHtml: false),
            'body' => $this->substitute($body, $workOrder, $actor, escapeHtml: true),
        ];
    }

    private function substitute(string $text, WorkOrder $workOrder, User $actor, bool $escapeHtml): string
    {
        return (string) preg_replace_callback(
            self::TOKEN_PATTERN,
            function (array $matches) use ($workOrder, $actor, $escapeHtml): string {
                $value = $this->resolve($matches[1], $matches[2], $workOrder, $actor);

                // D-4: the body escapes every substituted value (an
                // XSS/markup-injection guard on free-text fields, e.g. a
                // client's name); the subject stays plain text but never
                // carries a stray newline from a multi-line field such as
                // work_order.description.
                return $escapeHtml ? e($value) : str_replace(["\r\n", "\n", "\r"], ' ', $value);
            },
            $text,
        );
    }

    private function resolve(string $category, string $key, WorkOrder $workOrder, User $actor): string
    {
        return match ($category) {
            'work_order' => $this->workOrderField($key, $workOrder) ?? '',
            'sender' => $this->senderField($key, $actor) ?? '',
            default => $this->delegateToQuote($category, $key, $workOrder, $actor),
        };
    }

    private function workOrderField(string $key, WorkOrder $workOrder): ?string
    {
        return match ($key) {
            'code' => (string) $workOrder->code,
            'title' => (string) $workOrder->title,
            'type' => $workOrder->type?->value ?? '',
            'start_date' => $workOrder->start_date?->format('Y-m-d') ?? '',
            'callback_date' => $workOrder->callback_date?->format('Y-m-d') ?? '',
            'description' => (string) $workOrder->description,
            default => null,
        };
    }

    private function senderField(string $key, User $actor): ?string
    {
        return match ($key) {
            'name' => (string) $actor->name,
            'email' => (string) $actor->email,
            default => null,
        };
    }

    /**
     * A single `{category.key}` token, delegated verbatim to the shared
     * quote VariableResolver — empty when the category is outside D-4's
     * allow-list, or the commessa has no linked quote.
     */
    private function delegateToQuote(string $category, string $key, WorkOrder $workOrder, User $actor): string
    {
        if (! in_array($category, self::DELEGATED_CATEGORIES, true) || $workOrder->quote === null) {
            return '';
        }

        return $this->quoteResolver->substitute("{{$category}.{$key}}", $workOrder->quote, $actor);
    }
}
