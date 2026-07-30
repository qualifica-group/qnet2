<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Attachment;
use App\Models\DocumentLayout;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DocumentLayout
 */
class DocumentLayoutResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'module' => $this->module->value,
            'module_label' => __($this->module->labelKey()),
            'is_active' => $this->is_active,
            'is_default' => $this->is_default,
            'config' => $this->config,
            // Metadata only (no `data_uri`, spec 0069): the binaries are
            // fetched from the dedicated GET .../images endpoint so this
            // detail response never carries base64.
            'images' => $this->whenLoaded('images', fn () => $this->images
                ->map(fn (Attachment $image): array => [
                    'attachment_id' => $image->id,
                    'filename' => $image->original_name,
                    'mime_type' => $image->mime_type,
                    'size' => $image->size,
                ])
                ->all(), []),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
