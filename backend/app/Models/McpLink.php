<?php

namespace App\Models;

use App\Enums\McpLinkReason;
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
    protected $fillable = [
        'user_id',
        'api_client_id',
        'mcp_client',
        'credential_hash',
        'password_fingerprint',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'revoked_reason' => McpLinkReason::class,
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
