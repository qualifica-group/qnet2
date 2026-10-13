<?php

declare(strict_types=1);

namespace App\Services\PurchaseOrders;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\DocumentLayouts\Rendering\PurchaseOrders\PurchaseOrderRenderSubject;

/**
 * Resolves every `{category.key}` in a purchase order email template (spec
 * 0210). purchase_order/supplier/company/payment/totals come from the
 * purchase orders render subject (the same resolution and PII masking as the
 * PDF); sender is resolved here. Unknown tokens resolve to ''. The body
 * escapes every value; the subject stays plain text on a single line.
 */
final class PurchaseOrderEmailVariableResolver
{
    private const string TOKEN_PATTERN = '/\{([a-zA-Z][a-zA-Z0-9_]*)\.([a-zA-Z0-9_]+)\}/';

    /**
     * @return array{subject: string, body: string}
     */
    public function render(string $subject, string $body, PurchaseOrder $order, User $actor): array
    {
        $renderSubject = PurchaseOrderRenderSubject::for($order);
        $renderSubject->prepare();

        $resolve = fn (array $m): string => $this->resolve($m[1], $m[2], $renderSubject, $actor);

        return [
            'subject' => (string) preg_replace_callback(self::TOKEN_PATTERN, fn (array $m): string => str_replace(
                ["\r\n", "\n", "\r"], ' ', strip_tags($resolve($m)),
            ), $subject),
            'body' => (string) preg_replace_callback(self::TOKEN_PATTERN, fn (array $m): string => e($resolve($m)), $body),
        ];
    }

    private function resolve(string $category, string $key, PurchaseOrderRenderSubject $subject, User $actor): string
    {
        return match (true) {
            $category === 'sender' => match ($key) {
                'name' => (string) $actor->name,
                'email' => (string) $actor->email,
                default => '',
            },
            in_array($category, PurchaseOrderEmailVariableCatalog::DELEGATED_CATEGORIES, true) => $subject->resolveVariable($category, $key, $actor),
            default => '',
        };
    }
}
