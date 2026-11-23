<?php

namespace App\Models;

use App\Models\Concerns\LogsModelActivity;
use Database\Factories\ArchivioSmartSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Single-row ArchivioSmart connection (spec 0403, D-2). The token is encrypted at
 * rest and hidden from serialization. Audited through LogsModelActivity, which
 * excludes every hidden attribute: the token never reaches the activity log
 * (a token-only change therefore leaves no entry).
 */
#[Fillable(['base_url', 'token'])]
#[Hidden(['token'])]
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
        ];
    }

    public static function current(): ?self
    {
        return static::query()->orderBy('id')->first();
    }

    public function isConfigured(): bool
    {
        return filled($this->base_url) && filled($this->token);
    }
}
