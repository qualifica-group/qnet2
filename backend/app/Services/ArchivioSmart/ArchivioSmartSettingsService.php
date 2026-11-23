<?php

declare(strict_types=1);

namespace App\Services\ArchivioSmart;

use App\Models\ArchivioSmartSetting;
use Illuminate\Http\Client\ConnectionException;

/**
 * Read/save the single ArchivioSmart connection and probe it (spec 0403, D-2).
 */
class ArchivioSmartSettingsService
{
    public function __construct(private readonly ArchivioSmartClient $client) {}

    /**
     * The saved row, or an empty unsaved instance when nothing is configured.
     */
    public function current(): ArchivioSmartSetting
    {
        return ArchivioSmartSetting::current() ?? new ArchivioSmartSetting;
    }

    /**
     * Creates or updates the only row; a null token keeps the saved one.
     */
    public function save(string $baseUrl, ?string $token): ArchivioSmartSetting
    {
        $setting = $this->current();
        $setting->base_url = $baseUrl;

        if ($token !== null) {
            $setting->token = $token;
        }

        $setting->save();

        return $setting;
    }

    /**
     * Calls GET /me. Returns null when the connection is not configured. The
     * message never carries the token nor the remote body.
     *
     * @return array{ok: bool, http_status: int|null, message: string}|null
     */
    public function test(): ?array
    {
        $setting = ArchivioSmartSetting::current();

        if ($setting === null || ! $setting->isConfigured()) {
            return null;
        }

        try {
            $status = $this->client->me($setting)->status();
        } catch (ConnectionException) {
            return ['ok' => false, 'http_status' => null, 'message' => __('archivio_smart.network_error')];
        }

        if ($status === 200) {
            return ['ok' => true, 'http_status' => $status, 'message' => __('archivio_smart.connection_ok')];
        }

        return [
            'ok' => false,
            'http_status' => $status,
            'message' => __('archivio_smart.connection_failed', ['status' => $status]),
        ];
    }
}
