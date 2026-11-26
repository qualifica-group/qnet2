<?php

declare(strict_types=1);

namespace App\Http\Resources\AiAssistant;

use App\Enums\AiTurnStatus;
use App\Models\AiTurn;
use App\Services\AiAssistant\Preview\AiPreview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * State of a turn as the browser polls it (spec 0433, `AiTurn`). `preview` is the one the connector
 * returned for a write, as stored (never model text); the error text is the fixed one, translated in the turn language.
 *
 * @mixin AiTurn
 */
class AiTurnResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->ai_conversation_id,
            'status' => $this->status->value,
            'user_message_id' => $this->user_message_id,
            'assistant_text' => $this->assistant_text ?? '',
            'assistant_message' => $this->assistantMessagePayload($request),
            'steps' => $this->steps ?? [],
            'preview' => is_array($this->preview) ? AiPreview::fromArray($this->preview)->toArray() : null,
            'queue_position' => $this->status === AiTurnStatus::Queued ? $this->queuePosition() : null,
            'error_code' => $this->error_code?->value,
            'error_message' => $this->error_code?->message($this->locale),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function assistantMessagePayload(Request $request): ?array
    {
        $message = $this->assistant_message_id === null ? null : $this->assistantMessage;

        // The answer of the assistant never has files: the relation is set so the resource does not lazy-load it.
        return $message === null
            ? null
            : (new AiMessageResource($message->setRelation('attachments', collect())))->resolve($request);
    }

    private function queuePosition(): int
    {
        return AiTurn::query()->where('status', AiTurnStatus::Queued)->where('id', '<', $this->id)->count() + 1;
    }
}
