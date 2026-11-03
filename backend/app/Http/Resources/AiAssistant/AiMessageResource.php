<?php

namespace App\Http\Resources\AiAssistant;

use App\Models\AiMessage;
use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AiMessage
 */
class AiMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role->value,
            'content' => $this->content,
            'mentions' => $this->mentions ?? [],
            'attachments' => $this->attachments->map(static fn (Attachment $attachment): array => [
                'id' => $attachment->id,
                'original_name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'size' => $attachment->size,
            ])->values()->all(),
            'model' => $this->model,
            'input_tokens' => $this->input_tokens,
            'output_tokens' => $this->output_tokens,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
