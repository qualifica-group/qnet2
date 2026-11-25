<?php

declare(strict_types=1);

namespace App\Http\Resources\AiAssistant;

use App\Models\AiAssistantSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Settings of the assistant (spec 0433). The fixed core of the instructions and
 * the OpenRouter key never appear: only whether the key is present on the server.
 *
 * @mixin AiAssistantSetting
 */
class AiAssistantSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $saved = $this->updated_by_id !== null;

        return [
            'enabled' => $this->enabled,
            'model_id' => $this->model_id,
            'instructions' => $this->instructions,
            'instructions_max_chars' => (int) config('ai-assistant.settings.instructions_max_chars'),
            'spend_cap_person_usd' => $this->spend_cap_person_usd,
            'spend_cap_total_usd' => $this->spend_cap_total_usd,
            'routing' => $this->routing(),
            'openrouter_key_present' => filled(config('ai-assistant.openrouter.api_key')),
            'updated_at' => $saved ? $this->updated_at : null,
            'updated_by' => $saved && $this->updatedBy !== null
                ? ['id' => $this->updatedBy->id, 'name' => $this->updatedBy->name]
                : null,
        ];
    }
}
