<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts\Rendering;

use App\Enums\DocumentLayoutModule;
use App\Models\User;

/**
 * What a document is rendered ABOUT (spec 0195 D-5): the module-specific data
 * source behind the generic layout pipeline (DocumentGenerator -> DocxRenderer
 * -> block renderers). One implementation per layout module (Quote, Invoice);
 * the blocks never see the underlying model.
 *
 * Totals need no dedicated method: a products_table `totals.rows[].variable`
 * is an ordinary `{category.key}` token resolved by resolveVariable().
 */
interface DocumentRenderSubject
{
    public function module(): DocumentLayoutModule;

    /**
     * Eager-load every relation the subject reads while rendering
     * (Model::preventLazyLoading() is active outside production).
     */
    public function prepare(): void;

    /**
     * Value of one `{category.key}` variable for $actor. An unknown category
     * or key MUST return '' and never throw (generation must not fail on a
     * stale reference); PII masking, where applicable, happens here.
     */
    public function resolveVariable(string $category, string $key, User $actor): string;

    /**
     * The rows of a products_table `source`, in display order: one map per
     * row, already formatted display text keyed by column key (the keys
     * DocumentLayoutProductSources::for($module) lists for that source).
     *
     * @return array<int, array<string, string>>
     */
    public function productRows(string $source): array;
}
