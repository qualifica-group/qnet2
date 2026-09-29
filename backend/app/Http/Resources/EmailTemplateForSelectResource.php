<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\EmailTemplate;
use Illuminate\Http\Request;

/**
 * For-select projection of an EmailTemplate (GET /api/email-templates/for-select,
 * spec 0175, ADR 0011). Minimal by design: label = name, no subtitle/avatar/
 * meta — the composer resolves the picked template's subject/body via the
 * dedicated render-template endpoint, not from this projection.
 *
 * @mixin EmailTemplate
 */
class EmailTemplateForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->name,
        ];
    }
}
