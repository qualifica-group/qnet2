<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\DocumentLayouts\CreateDocumentLayoutData;
use App\DataObjects\DocumentLayouts\UpdateDocumentLayoutData;
use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Enums\DocumentLayoutModule;
use App\Models\Attachment;
use App\Models\DocumentLayout;
use App\Models\Quote;
use App\Services\DocumentLayouts\DocumentLayoutDefaultManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Business logic for the `document-layouts` resource (spec 0069): a
 * consumer-agnostic, block-based document layout (name/code/description/
 * module/is_active/is_default/config). `is_default` is never mass-assigned
 * directly — every write path resolves/validates it through
 * DocumentLayoutDefaultManager (D-7), inside the same DB::transaction as the
 * rest of the write, so the "0 or 1 default per module" invariant is never
 * observable as violated even transiently.
 */
class DocumentLayoutService
{
    public function __construct(
        private readonly DocumentLayoutDefaultManager $defaultManager,
        private readonly AttachmentService $attachmentService,
    ) {}

    /**
     * Shared by show (controller)/create/update — eager-loads `images` so the
     * detail response's `images` metadata array (spec 0069, GET show) never
     * lazy-loads (App\Providers\AppServiceProvider::preventLazyLoading()
     * outside production).
     */
    public function loadDetail(DocumentLayout $documentLayout): DocumentLayout
    {
        return $documentLayout->load('images');
    }

    public function create(CreateDocumentLayoutData $data): DocumentLayout
    {
        return DB::transaction(function () use ($data): DocumentLayout {
            // Step 1: resolve the effective is_default (D-7a/c) BEFORE insert.
            $isDefault = $this->defaultManager->resolveIsDefaultForCreate($data->module, $data->isDefault, $data->isActive);

            // Step 2: persist the row with the resolved flag.
            $documentLayout = DocumentLayout::create([...$data->attributes(), 'is_default' => $isDefault]);

            // Step 3: D-7b — unset the flag on any previous default of the module.
            if ($isDefault) {
                $this->defaultManager->clearOtherDefaults($documentLayout);
            }

            return $this->loadDetail($documentLayout);
        });
    }

    public function update(DocumentLayout $documentLayout, UpdateDocumentLayoutData $data): DocumentLayout
    {
        return DB::transaction(function () use ($documentLayout, $data): DocumentLayout {
            $requestedIsActive = $data->isActiveSubmitted ? $data->isActive : null;
            $requestedIsDefault = $data->isDefaultSubmitted ? $data->isDefault : null;

            // Step 1: validate the {is_active, is_default} transition against
            // the CURRENTLY persisted state (D-7c/d/e) before writing anything.
            $this->defaultManager->assertUpdateTransitionValid($documentLayout, $requestedIsActive, $requestedIsDefault);

            // Step 2: persist every plain submitted attribute (never
            // `is_default` — applied separately below). Unconditional save:
            // fires the model's saved event even when nothing native changed,
            // so the HasCustomFields write pipeline (spec 0021) still
            // persists a custom-fields-only edit.
            $documentLayout->fill($data->submittedAttributes())->save();

            // Step 3: D-7b — promote to default and clear every sibling, only
            // when the client explicitly requested it (already validated above).
            if ($requestedIsDefault === true) {
                $documentLayout->forceFill(['is_default' => true])->save();
                $this->defaultManager->clearOtherDefaults($documentLayout);
            }

            return $this->loadDetail($documentLayout->fresh());
        });
    }

    /**
     * Delete, guarded by TWO checks, in this frozen order (spec 0070, D-7):
     * (1) D-7's default invariant (AC-025..028) — a predefinito layout blocks
     * deletion unless it is the module's only layout; (2) the usage guard
     * below — a layout referenced by at least one Quote cannot be deleted at
     * all. A layout that is BOTH default and in use fails on (1), the more
     * specific/earlier rule (AC-281). Every uploaded image is removed WITH
     * the layout (attachment row + binary on disk, via
     * AttachmentService::delete()) — DocumentLayout does not `use
     * HasAttachments` (its `images()` relation is scoped to `layout_image`,
     * wave 1), so this loop is the cascade, not a framework hook.
     */
    public function delete(DocumentLayout $documentLayout): void
    {
        $this->defaultManager->assertDeletable($documentLayout);
        $this->assertNotInUse($documentLayout);

        $documentLayout->images()->get()->each(
            fn (Attachment $attachment) => $this->attachmentService->delete($attachment)
        );

        $documentLayout->delete();
    }

    /**
     * D-7's usage guard (spec 0070): a layout referenced by at least one
     * Quote is not deletable — only deactivatable. The count is read with a
     * single `count()` query, never hydrating the referencing quotes
     * (AC-283). Thrown as a ValidationException so the controller's existing
     * generic Throwable handling (BaseApiController::handleControllerException)
     * needs no special case, exactly like DocumentLayoutDefaultManager's own
     * checks above.
     */
    private function assertNotInUse(DocumentLayout $documentLayout): void
    {
        $usageCount = Quote::query()->where('layout_id', $documentLayout->id)->count();

        if ($usageCount > 0) {
            throw ValidationException::withMessages([
                'quotes' => [__('document_layouts.layout_in_use', ['count' => $usageCount])],
            ]);
        }
    }

    /**
     * Minimal, searchable, paginated document layout list for the for-select
     * standard (ADR 0011), scoped to a single $module (AC-081: the request's
     * `module` is required, so it is resolved by the FormRequest and passed
     * here explicitly rather than folded into the shared ForSelectQuery DTO —
     * every OTHER for-select consumer has no such mandatory dimension).
     * Only `is_active = true` rows are eligible, predefinito first
     * (AC-080).
     */
    public function forSelect(DocumentLayoutModule $module, ForSelectQuery $query): ForSelectResult
    {
        $base = DocumentLayout::query()
            ->select(['id', 'name', 'code', 'module', 'is_default'])
            ->where('module', $module->value)
            ->where('is_active', true);

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        $total = (clone $base)->count();

        /** @var Collection<int, DocumentLayout> $page */
        $page = $base->orderByDesc('is_default')
            ->orderBy('name')
            ->orderBy('id')
            ->offset($query->offset)
            ->limit($query->limit)
            ->get();

        $items = $this->appendHydratedIds($page, $module, $query);

        return new ForSelectResult(
            items: $items,
            total: $total,
            offset: $query->offset,
            limit: $query->limit,
        );
    }

    /**
     * Append the explicitly-requested `ids[]` (edit-mode hydration) that are
     * not already on the page, deduplicated. They bypass search AND the
     * `is_active` filter (AC-082: a consumer keeps showing its currently
     * assigned layout even after it is deactivated), scoped to the same
     * $module. Total is unaffected.
     *
     * @param  Collection<int, DocumentLayout>  $page
     * @return Collection<int, DocumentLayout>
     */
    private function appendHydratedIds(Collection $page, DocumentLayoutModule $module, ForSelectQuery $query): Collection
    {
        if (! $query->hasIds()) {
            return $page;
        }

        $presentIds = $page->pluck('id')->all();
        $missingIds = array_values(array_diff($query->ids, $presentIds));

        if ($missingIds === []) {
            return $page;
        }

        /** @var Collection<int, DocumentLayout> $hydrated */
        $hydrated = DocumentLayout::query()
            ->select(['id', 'name', 'code', 'module', 'is_default'])
            ->where('module', $module->value)
            ->whereIn('id', $missingIds)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }
}
