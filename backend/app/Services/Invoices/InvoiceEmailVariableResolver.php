<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use App\Models\Invoice;
use App\Models\User;
use App\Services\DocumentLayouts\Rendering\Invoices\InvoiceRenderSubject;
use Illuminate\Support\Carbon;

/**
 * Resolves every `{category.key}` in an invoice email template (spec 0195,
 * D-13). invoice/customer/company/payment/totals come from the invoices
 * render subject (the same resolution and PII masking as the PDF); sender and
 * reminder are resolved here. Unknown tokens resolve to ''. The body escapes
 * every value except the reminder list, which InvoiceEmailOverdue builds
 * already escaped; the subject stays plain text on a single line.
 */
final class InvoiceEmailVariableResolver
{
    private const string TOKEN_PATTERN = '/\{([a-zA-Z][a-zA-Z0-9_]*)\.([a-zA-Z0-9_]+)\}/';

    public function __construct(private readonly InvoiceEmailOverdue $overdue) {}

    /**
     * @return array{subject: string, body: string}
     */
    public function render(string $subject, string $body, Invoice $invoice, User $actor): array
    {
        $invoice->loadMissing('installments');
        $renderSubject = InvoiceRenderSubject::for($invoice);
        $renderSubject->prepare();

        $resolve = fn (array $m): string => $this->resolve($m[1], $m[2], $invoice, $renderSubject, $actor);

        return [
            'subject' => (string) preg_replace_callback(self::TOKEN_PATTERN, fn (array $m): string => str_replace(
                ["\r\n", "\n", "\r"], ' ', strip_tags($resolve($m)),
            ), $subject),
            'body' => (string) preg_replace_callback(self::TOKEN_PATTERN, fn (array $m): string => $m[1].'.'.$m[2] === 'reminder.overdue_installments'
                ? $resolve($m)
                : e($resolve($m)), $body),
        ];
    }

    private function resolve(string $category, string $key, Invoice $invoice, InvoiceRenderSubject $subject, User $actor): string
    {
        $today = Carbon::today();

        return match (true) {
            $category === 'sender' => match ($key) {
                'name' => (string) $actor->name,
                'email' => (string) $actor->email,
                default => '',
            },
            $category === 'reminder' => match ($key) {
                'overdue_installments' => $this->overdue->html($invoice, $today),
                'overdue_amount' => $this->overdue->amount($invoice, $today),
                default => '',
            },
            in_array($category, InvoiceEmailVariableCatalog::DELEGATED_CATEGORIES, true) => $subject->resolveVariable($category, $key, $actor),
            default => '',
        };
    }
}
