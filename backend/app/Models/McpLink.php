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
    /** Name prefix of the user tokens an exchange issues; a refresh keeps the name. */
    public const string TOKEN_NAME_PREFIX = 'mcp-link-';

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

    public static function tokenName(int $linkId): string
    {
        return self::TOKEN_NAME_PREFIX.$linkId;
    }

    public static function linkIdFromTokenName(string $name): ?int
    {
        return preg_match('/^'.preg_quote(self::TOKEN_NAME_PREFIX, '/').'(\d+)$/', $name, $m) === 1 ? (int) $m[1] : null;
    }

    /**
     * Alive for this user: not revoked, not expired, user active, password unchanged since the opening.
     */
    public function isAliveFor(User $user): bool
    {
        return $this->revoked_at === null
            && $this->expires_at->isFuture()
            && $user->is_active
            && hash_equals($this->password_fingerprint, self::fingerprint($user->password));
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
