<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use Database\Factories\AiConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Per-user AI assistant conversation (spec 0347, D-5).
 *
 * The DB cascade removes the messages but fires no model event, so the
 * attachment binaries on disk would be orphaned: always remove a conversation
 * through deleteWithFiles().
 */
#[Fillable(['user_id', 'title', 'last_message_at'])]
class AiConversation extends BaseModel
{
    /** @use HasFactory<AiConversationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'int',
            'last_message_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class)->orderBy('id');
    }

    /**
     * Delete the conversation, its messages, their attachment rows and files.
     * Each message is deleted through Eloquent so HasAttachments removes the
     * binaries (AC-016, AC-017).
     */
    public function deleteWithFiles(): void
    {
        DB::transaction(function (): void {
            $this->messages()->get()->each->delete();
            $this->delete();
        });
    }
}
