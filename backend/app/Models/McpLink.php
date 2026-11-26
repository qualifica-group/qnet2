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
    /** Name prefix of the user tokens an exchange issues: a label only, any login can choose the same name. */
    public const string TOKEN_NAME_PREFIX = 'mcp-link-';

    /**
     * Ability that marks the tokens of a link. Only the exchange sets abilities: login,
     * client-login and refresh all issue ['*'], so a caller cannot obtain this marker.
     */
    public const string ABILITY_PREFIX = 'mcp-link:';

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

    /**
     * '*' keeps the token as capable as an ordinary one (tokenCan passes on it); the second ability is the marker.
     *
     * @return array<int, string>
     */
    public static function tokenAbilities(int $linkId): array
    {
        return ['*', self::ABILITY_PREFIX.$linkId];
    }

    /**
     * @param  array<int, string>  $abilities
     */
    public static function linkIdFromAbilities(array $abilities): ?int
    {
        foreach ($abilities as $ability) {
            if (preg_match('/^'.preg_quote(self::ABILITY_PREFIX, '/').'(\d+)$/', $ability, $m) === 1) {
                return (int) $m[1];
            }
        }

        return null;
    }

    /**
     * Alive for this user: not revoked, not expired, user active, password unchanged since the opening.
     * The session bond of an assistant link is checked apart (AssistantLink::sessionAliveFor).
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
