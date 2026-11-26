<?php

namespace App\Models;

use App\Enums\AiTurnErrorCode;
use App\Enums\AiTurnStatus;
use App\Models\Abstracts\BaseModel;
use Database\Factories\AiTurnFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One run of the AI assistant agent (spec 0433): the state the browser polls.
 * `steps` is a list of {index, tool, label, state}; labels are already translated.
 */
#[Fillable([
    'ai_conversation_id', 'user_message_id', 'assistant_message_id', 'personal_access_token_id', 'status',
    'locale', 'page_guide_text', 'assistant_text', 'steps', 'preview', 'resume_state', 'usage', 'error_code', 'started_at', 'finished_at',
])]
class AiTurn extends BaseModel
{
    /** @use HasFactory<AiTurnFactory> */
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'ai_conversation_id' => 'int',
            'user_message_id' => 'int',
            'assistant_message_id' => 'int',
            'personal_access_token_id' => 'int',
            'status' => AiTurnStatus::class,
            'error_code' => AiTurnErrorCode::class,
            'steps' => 'array',
            'preview' => 'array',
            'resume_state' => 'array',
            'usage' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }

    public function userMessage(): BelongsTo
    {
        return $this->belongsTo(AiMessage::class, 'user_message_id');
    }

    public function assistantMessage(): BelongsTo
    {
        return $this->belongsTo(AiMessage::class, 'assistant_message_id');
    }
}
