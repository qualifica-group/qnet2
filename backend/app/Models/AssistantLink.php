<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Binds the MCP link of the AI assistant to the Sanctum token of the session it
 * was opened from, and keeps its credential encrypted between turns. Not
 * audited and not serialized: the credential never leaves the server.
 */
class AssistantLink extends Model
{
    protected $fillable = ['user_id', 'mcp_link_id', 'personal_access_token_id', 'credential'];

    protected $hidden = ['credential'];

    protected function casts(): array
    {
        return ['credential' => 'encrypted'];
    }

    /**
     * Same validity rule as Sanctum's guard: not past `expires_at`, not older than SANCTUM_EXPIRATION.
     */
    public static function tokenIsUsable(?PersonalAccessToken $token): bool
    {
        if ($token === null || ($token->expires_at !== null && $token->expires_at->isPast())) {
            return false;
        }

        $expiration = (int) config('sanctum.expiration');

        return $expiration <= 0 || $token->created_at->gt(now()->subMinutes($expiration));
    }

    /**
     * Explicit read, not the relation: the token row may be gone and models stay lazy-loading safe.
     */
    public function sessionIsAlive(): bool
    {
        return $this->personal_access_token_id !== null
            && self::tokenIsUsable(PersonalAccessToken::query()->find($this->personal_access_token_id));
    }

    public function link(): BelongsTo
    {
        return $this->belongsTo(McpLink::class, 'mcp_link_id');
    }
}
