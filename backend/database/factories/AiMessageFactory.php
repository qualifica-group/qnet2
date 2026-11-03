<?php

namespace Database\Factories;

use App\Enums\AiMessageRole;
use App\Models\AiConversation;
use App\Models\AiMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiMessage>
 */
class AiMessageFactory extends Factory
{
    protected $model = AiMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ai_conversation_id' => AiConversation::factory(),
            'role' => AiMessageRole::User,
            'content' => fake()->sentence(),
            'mentions' => null,
            'page_guide_key' => null,
            'model' => null,
            'input_tokens' => null,
            'output_tokens' => null,
        ];
    }

    public function assistant(): static
    {
        return $this->state(fn (): array => [
            'role' => AiMessageRole::Assistant,
            'model' => 'test-model',
            'input_tokens' => 10,
            'output_tokens' => 20,
        ]);
    }
}
