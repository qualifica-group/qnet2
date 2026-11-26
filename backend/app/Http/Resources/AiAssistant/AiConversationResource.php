<?php

namespace App\Http\Resources\AiAssistant;

use App\Models\AiConversation;
use App\Services\AiAssistant\Memory\AiConversationMemory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `active_turn` appears only in the detail (spec 0433): the browser that reloads the page resumes polling it.
 * `last_turn` is the newest turn, only when it is closed and carries a preview: the chat shows that card again (0433).
 * `propose_new_conversation` is true once the conversation has been summarised the configured number of times (D-5).
 *
 * @mixin AiConversation
 */
class AiConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'propose_new_conversation' => app(AiConversationMemory::class)->proposesNewConversation((int) $this->summary_count),
            'messages' => AiMessageResource::collection($this->whenLoaded('messages')),
            'active_turn' => $this->whenLoaded('activeTurn', fn (): ?AiTurnResource => $this->activeTurn === null ? null : new AiTurnResource($this->activeTurn)),
            'last_turn' => $this->whenLoaded('lastTurn', fn (): ?AiTurnResource => $this->lastTurnWithDecidedPreview()),
        ];
    }

    private function lastTurnWithDecidedPreview(): ?AiTurnResource
    {
        $turn = $this->lastTurn;

        return $turn !== null && $turn->status->isFinal() && is_array($turn->preview) ? new AiTurnResource($turn) : null;
    }
}
