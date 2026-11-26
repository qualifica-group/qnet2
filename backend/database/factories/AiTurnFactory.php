<?php

namespace Database\Factories;

use App\Enums\AiMessageRole;
use App\Enums\AiTurnStatus;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiTurn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiTurn>
 */
class AiTurnFactory extends Factory
{
    protected $model = AiTurn::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ai_conversation_id' => AiConversation::factory(),
            'user_message_id' => fn (array $attributes): int => AiMessage::factory()->create([
                'ai_conversation_id' => $attributes['ai_conversation_id'],
                'role' => AiMessageRole::User,
            ])->id,
            'status' => AiTurnStatus::Queued,
            'locale' => 'en',
        ];
    }

    public function status(AiTurnStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }
}
