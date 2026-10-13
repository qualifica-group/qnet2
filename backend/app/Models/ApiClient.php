<?php

namespace App\Models;

use App\Models\Concerns\LogsModelActivity;
use Database\Factories\ApiClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

/**
 * Integration client of the QNet API (specs 0209, 0210). Its key is the Sanctum
 * token of its technical user; the tokens bound to it (`api_client_id`) are the
 * key and the user tokens issued through client-login. Extends Model directly
 * (like User): custom fields make no sense on it.
 */
#[Fillable([
    'name',
    'description',
    'rate_limit_per_minute',
    'expires_at',
    'is_active',
    'key_last_four',
    'created_by',
])]
class ApiClient extends Model
{
    /** @use HasFactory<ApiClientFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
            'rate_limit_per_minute' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Technical user that authors the writes made with the client key. Not
     * fillable: only ApiClientService sets it.
     */
    public function serviceUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'service_user_id');
    }

    /**
     * Every token bound to the client: its key and the client-login user tokens.
     *
     * @return HasMany<PersonalAccessToken, $this>
     */
    public function tokens(): HasMany
    {
        return $this->hasMany(Sanctum::personalAccessTokenModel(), 'api_client_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function effectiveRateLimitPerMinute(): int
    {
        return $this->rate_limit_per_minute ?? (int) config('external-api.rate_limit.default');
    }
}
