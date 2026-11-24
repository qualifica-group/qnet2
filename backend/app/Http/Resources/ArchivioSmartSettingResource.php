<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ArchivioSmartSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Never exposes the token nor the webhook secret, not even partially: only whether one is saved.
 *
 * @mixin ArchivioSmartSetting
 */
class ArchivioSmartSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'base_url' => $this->base_url,
            'has_token' => filled($this->token),
            'has_webhook_secret' => $this->hasWebhookSecret(),
            'webhook_url' => route('webhooks.archivio-smart'),
            'updated_at' => $this->updated_at,
        ];
    }
}
