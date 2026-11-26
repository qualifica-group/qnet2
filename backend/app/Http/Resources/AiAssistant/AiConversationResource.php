<?php

namespace App\Http\Resources\AiAssistant;

use App\Models\AiConversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `active_turn` appears only in the detail (spec 0433): the browser that reloads the page resumes polling it.
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
            'messages' => AiMessageResource::collection($this->whenLoaded('messages')),
            'active_turn' => $this->whenLoaded('activeTurn', fn (): ?AiTurnResource => $this->activeTurn === null ? null : new AiTurnResource($this->activeTurn)),
        ];
    }
}
