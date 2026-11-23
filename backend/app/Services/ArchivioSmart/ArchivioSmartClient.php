<?php

declare(strict_types=1);

namespace App\Services\ArchivioSmart;

use App\Models\ArchivioSmartSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Thin HTTP client for the ArchivioSmart public API (spec 0403).
 *
 * No internal retry (the job owns attempts and backoff) and no throw on 4xx/5xx:
 * the caller interprets the Response. A ConnectionException propagates. The token
 * lives only in the Authorization header; this class never logs request or response.
 */
class ArchivioSmartClient
{
    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws ConnectionException
     */
    public function upsertChecklist(ArchivioSmartSetting $setting, string $externalId, array $payload): Response
    {
        return $this->request($setting)->put($this->checklistPath($externalId), $payload);
    }

    /**
     * @throws ConnectionException
     */
    public function deleteChecklist(ArchivioSmartSetting $setting, string $externalId): Response
    {
        return $this->request($setting)->delete($this->checklistPath($externalId));
    }

    /**
     * @throws ConnectionException
     */
    public function me(ArchivioSmartSetting $setting): Response
    {
        return $this->request($setting)->get('/me');
    }

    private function request(ArchivioSmartSetting $setting): PendingRequest
    {
        return Http::baseUrl(rtrim((string) $setting->base_url, '/'))
            ->withToken((string) $setting->token)
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('archivio-smart.timeout'))
            ->connectTimeout((int) config('archivio-smart.connect_timeout'));
    }

    private function checklistPath(string $externalId): string
    {
        return '/checklists/'.rawurlencode($externalId);
    }
}
