<?php

namespace App\Models;

use App\Models\Concerns\LogsModelActivity;
use Database\Factories\ArchivioSmartSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Single-row ArchivioSmart connection (spec 0403, D-2). The token and the webhook
 * secret (spec 0428, D-6) are encrypted at rest and hidden from serialization. Audited through LogsModelActivity, which
 * excludes every hidden attribute: the token never reaches the activity log
 * (a secret-only change therefore leaves no entry).
 */
#[Fillable(['base_url', 'token', 'webhook_secret'])]
#[Hidden(['token', 'webhook_secret'])]
class ArchivioSmartSetting extends Model
{
    /** @use HasFactory<ArchivioSmartSettingFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'webhook_secret' => 'encrypted',
        ];
    }

    public static function current(): ?self
    {
        return static::query()->orderBy('id')->first();
    }

    public function hasWebhookSecret(): bool
    {
        return filled($this->webhook_secret);
    }

    public function isConfigured(): bool
    {
        return filled($this->base_url) && filled($this->token);
    }
}
