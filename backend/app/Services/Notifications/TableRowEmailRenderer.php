<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\EmailTemplate;
use App\Models\Quote;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\DocumentLayouts\Rendering\VariableResolver;
use App\Services\DocumentLayouts\Rendering\VariableTokens;
use App\Services\OutboundEmails\WorkOrderEmailVariableResolver;
use Illuminate\Database\Eloquent\Model;

/**
 * Renders the "Modello email" a table row event sends (spec 0390) against the
 * record the row belongs to, with the SAME resolvers the email composer uses:
 * a Commessa through WorkOrderEmailVariableResolver, an Offerta through the
 * quote VariableResolver. A record with no resolver of its own gets its
 * `{category.key}` placeholders emptied rather than printed raw.
 */
final class TableRowEmailRenderer
{
    public function __construct(
        private readonly WorkOrderEmailVariableResolver $workOrderResolver,
        private readonly VariableResolver $quoteResolver,
    ) {}

    /**
     * @return array{subject: string, body: string} subject as plain text, body as HTML
     */
    public function render(EmailTemplate $template, Model $record, User $actor): array
    {
        $subject = (string) $template->subject;
        $body = (string) $template->body;

        if ($record instanceof WorkOrder) {
            return $this->workOrderResolver->render($subject, $body, $record, $actor);
        }

        $resolve = $record instanceof Quote
            ? fn (string $category, string $key): string => $this->quoteResolver->resolve($category, $key, $record, $actor)
            : fn (): string => '';

        return [
            'subject' => str_replace(["\r\n", "\n", "\r"], ' ', VariableTokens::replace($subject, $resolve)),
            'body' => VariableTokens::replace($body, fn (string $category, string $key): string => e($resolve($category, $key))),
        ];
    }
}
