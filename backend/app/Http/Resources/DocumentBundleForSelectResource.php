<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\DocumentBundle;
use Illuminate\Http\Request;

/**
 * For-select projection of a DocumentBundle (GET /api/document-bundles/for-select,
 * spec 0175, ADR 0011). `meta.files_count` lets the composer's "Da modello
 * documenti" picker preview the bundle's file count before attaching it —
 * comes off DocumentBundleService::forSelect()'s own
 * `withCount(['attachments as files_count'])` projection.
 *
 * @mixin DocumentBundle
 */
class DocumentBundleForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->name,
            'meta' => ['files_count' => (int) $this->files_count],
        ];
    }
}
