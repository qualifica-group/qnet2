<?php

namespace App\Http\Resources;

use App\Models\ApiClient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @mixin ApiClient
 *
 * Never carries the key or its hash. Relies on ApiClientService::loadDetail()
 * (or the table baseQuery) having loaded `creator`, `serviceUser` and the max token
 * `last_used_at`, so resolving it never N+1s.
 */
class ApiClientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lastUsedAt = $this->getAttribute('tokens_max_last_used_at');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'rate_limit_per_minute' => $this->rate_limit_per_minute,
            'effective_rate_limit_per_minute' => $this->effectiveRateLimitPerMinute(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'is_active' => $this->is_active,
            'is_expired' => $this->isExpired(),
            'key_last_four' => $this->key_last_four,
            'last_used_at' => $lastUsedAt === null ? null : Carbon::parse($lastUsedAt)->toIso8601String(),
            'service_user' => [
                'id' => $this->serviceUser->id,
                'name' => $this->serviceUser->name,
            ],
            'created_by' => $this->creator === null ? null : [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
