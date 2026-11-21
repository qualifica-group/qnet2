<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Long-lived link between a user and an MCP client, opened through an API
 * client key. Only the fingerprint of the credential is stored.
 */
class McpLink extends Model
{
    public const string REVOKED_BY_CLIENT = 'revoked';

    public const string REVOKED_PASSWORD_CHANGED = 'password_changed';

    public const string REVOKED_USER_DEACTIVATED = 'user_deactivated';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public static function fingerprint(string $value): string
    {
        return hash('sha256', $value);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tokens(): BelongsToMany
    {
        return $this->belongsToMany(PersonalAccessToken::class, 'mcp_link_tokens');
    }
}
