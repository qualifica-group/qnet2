<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ArchivioSmartSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Never exposes the token, not even partially: only whether one is saved.
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
            'updated_at' => $this->updated_at,
        ];
    }
}
