<?php

namespace App\Models;

use App\Enums\DocumentLayoutModule;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\DocumentLayoutFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A reusable, block-based document layout (spec 0069): three zones
 * (header/body/footer), each a list of typed blocks, persisted as the `config`
 * JSON tree — validated server-side by
 * App\Services\DocumentLayouts\DocumentLayoutConfigValidator against a closed
 * allow-list, never interpolated/executed. `code` is globally unique and
 * immutable after create; `name` only needs to be unique WITHIN `module`
 * (enforced by the composite unique index, mirrored client-side by the
 * FormRequest). `is_default` carries the "exactly 0 or 1 default per module"
 * invariant, enforced transactionally by
 * App\Services\DocumentLayouts\DocumentLayoutDefaultManager (spec 0070's
 * write path) — never by a DB constraint (no portable partial unique index
 * across MySQL+SQLite).
 *
 * NOTE for the images() relation below: it requires `document_layout` to be
 * registered in `Relation::enforceMorphMap()` (App\Providers\AppServiceProvider::boot),
 * which this spec's wave 1 already added — see that file's docblock comment
 * for why the registration could not be deferred (LogsModelActivity itself
 * needs it on every create/update, not just image uploads).
 */
#[Fillable(['name', 'code', 'description', 'module', 'is_active', 'is_default', 'config'])]
class DocumentLayout extends BaseModel
{
    /** @use HasFactory<DocumentLayoutFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * The `attachments.collection` value scoping a layout's own uploaded
     * images (logos / carta intestata), as opposed to any other collection a
     * future feature might attach to the same polymorphic table.
     */
    public const string IMAGE_COLLECTION = 'layout_image';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'module' => DocumentLayoutModule::class,
            'is_active' => 'bool',
            'is_default' => 'bool',
            'config' => 'array',
        ];
    }

    /**
     * The images (logos / carta intestata) uploaded to this layout, scoped to
     * `layout_image` so any other future collection on the `attachments`
     * table never leaks in here.
     */
    public function images(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->where('collection', self::IMAGE_COLLECTION);
    }
}
