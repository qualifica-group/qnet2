<?php

namespace App\Models;

use App\Enums\AiMessageRole;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\HasAttachments;
use Database\Factories\AiMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

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
     * Spec 0433, D-10: the message that starts a turn photographs the person's business functions
     * when it is saved, so the cost of every call of the turn follows them even if the membership
     * changes while the turn waits.
     */
    protected static function booted(): void
    {
        static::creating(function (self $message): void {
            if ($message->role !== AiMessageRole::User || $message->business_function_ids !== null) {
                return;
            }
            $userId = AiConversation::query()->whereKey($message->ai_conversation_id)->value('user_id');
            $message->business_function_ids = $userId === null ? [] : DB::table('business_function_user')
                ->where('user_id', $userId)->orderBy('business_function_id')->pluck('business_function_id')
                ->map(fn ($id): int => (int) $id)->all();
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'business_function_ids' => 'array',
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
