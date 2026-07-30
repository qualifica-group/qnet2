<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts;

use App\Enums\DocumentLayoutModule;
use App\Models\DocumentLayout;
use App\Models\Quote;

/**
 * Resolves WHICH DocumentLayout a Quote's `.docx` is generated against (spec
 * 0070, D-3): the quote's own `layout_id` wins outright, even when that
 * layout has since been deactivated — a quote keeps the layout it was made
 * with, it is never silently swapped for the module's current default. Only
 * when `layout_id` is null does the `quotes` module's current ACTIVE default
 * apply. Returns null when neither exists (AC-260): the caller (currently
 * QuoteDocumentController) maps that to a 422 — this class never throws.
 */
final class QuoteDocumentLayoutResolver
{
    public function resolve(Quote $quote): ?DocumentLayout
    {
        // Step 1: an explicit layout always wins, active or not (D-3).
        if ($quote->layout_id !== null) {
            return $quote->layout()->first();
        }

        // Step 2: fall back to the quotes module's current active default.
        return DocumentLayout::query()
            ->where('module', DocumentLayoutModule::Quotes->value)
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();
    }
}
