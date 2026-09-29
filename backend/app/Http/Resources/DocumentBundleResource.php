<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DocumentBundle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `files_count` relies on the caller (DocumentBundleController via
 * DocumentBundleService::withFilesCount()) always having run
 * `loadCount(['attachments as files_count'])` first — never re-queried here
 * (mirrors OpportunityResource's `quotes_count`).
 *
 * @mixin DocumentBundle
 */
class DocumentBundleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'files_count' => (int) $this->files_count,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
