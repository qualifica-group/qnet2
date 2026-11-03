<?php

namespace App\Models;

use App\Enums\AiMessageRole;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\HasAttachments;
use Database\Factories\AiMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One turn of an AI assistant conversation (spec 0347). User messages own
 * their uploaded files through HasAttachments.
 */
#[Fillable([
    'ai_conversation_id', 'role', 'content', 'mentions', 'page_guide_key',
    'model', 'input_tokens', 'output_tokens',
])]
class AiMessage extends BaseModel
{
    /** @use HasFactory<AiMessageFactory> */
    use HasAttachments, HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'ai_conversation_id' => 'int',
            'role' => AiMessageRole::class,
            'mentions' => 'array',
            'input_tokens' => 'int',
            'output_tokens' => 'int',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }
}
