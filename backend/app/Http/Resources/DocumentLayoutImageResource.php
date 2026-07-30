<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * A document layout image (spec 0069, GET/POST .../images): unlike
 * DocumentLayoutResource's own `images` metadata array, this one carries the
 * `data_uri` binary — the dedicated endpoint exists precisely so the base64
 * payload never rides along with the layout detail/table response.
 *
 * `data_uri` mirrors the repo's established pattern (CompanySite::logoDataUri(),
 * User::avatarDataUri()): the private `local` disk is read and base64-encoded
 * behind authentication + permission, never served as a public URL.
 *
 * @mixin Attachment
 */
class DocumentLayoutImageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'attachment_id' => $this->id,
            'filename' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'data_uri' => $this->dataUri(),
        ];
    }

    private function dataUri(): ?string
    {
        $disk = Storage::disk($this->disk);

        if (! $disk->exists($this->path)) {
            return null;
        }

        return 'data:'.$this->mime_type.';base64,'.base64_encode($disk->get($this->path));
    }
}
