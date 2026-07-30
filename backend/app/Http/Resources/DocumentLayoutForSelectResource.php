<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\DocumentLayout;
use Illuminate\Http\Request;

/**
 * For-select projection of a DocumentLayout (GET
 * /api/document-layouts/for-select, spec 0069).
 *
 * `label` = name, `subtitle` = code (always present, `code` is required and
 * unique), `meta.is_default` lets a consumer form pre-select/flag the
 * module's predefinito layout without a second lookup — `meta.code` is
 * duplicated from `subtitle` per the frozen contract shape.
 *
 * @mixin DocumentLayout
 */
class DocumentLayoutForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->name,
            'subtitle' => $this->code,
            'meta' => ['is_default' => $this->is_default, 'code' => $this->code],
        ];
    }
}
