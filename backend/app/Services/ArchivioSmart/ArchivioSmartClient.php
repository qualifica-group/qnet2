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
     * @param  array<string, mixed>  $payload
     *
     * @throws ConnectionException
     */
    public function upsertCategory(ArchivioSmartSetting $setting, string $externalId, array $payload): Response
    {
        return $this->request($setting)->put($this->categoryPath($externalId), $payload);
    }

    /**
     * @throws ConnectionException
     */
    public function deleteCategory(ArchivioSmartSetting $setting, string $externalId): Response
    {
        return $this->request($setting)->delete($this->categoryPath($externalId));
    }

    /**
     * Creates or edits a document request (spec 0428): the same idempotent PUT.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws ConnectionException
     */
    public function upsertRequest(ArchivioSmartSetting $setting, string $externalId, array $payload): Response
    {
        return $this->request($setting)->put($this->requestPath($externalId), $payload);
    }

    /**
     * @throws ConnectionException
     */
    public function remindRequest(ArchivioSmartSetting $setting, string $externalId): Response
    {
        return $this->request($setting)->post($this->requestPath($externalId).'/remind');
    }

    /**
     * @throws ConnectionException
     */
    public function cancelRequest(ArchivioSmartSetting $setting, string $externalId): Response
    {
        return $this->request($setting)->post($this->requestPath($externalId).'/cancel');
    }

    /**
     * @throws ConnectionException
     */
    public function getRequest(ArchivioSmartSetting $setting, string $externalId): Response
    {
        return $this->request($setting)->get($this->requestPath($externalId));
    }

    /**
     * Manual approval or rejection of an item (spec 0429, D-2). The note and the
     * reviewer name travel only in the body.
     *
     * @throws ConnectionException
     */
    public function reviewItem(ArchivioSmartSetting $setting, string $externalId, int $itemId, string $decision, ?string $note, string $reviewerName): Response
    {
        return $this->request($setting)->post(
            $this->requestPath($externalId).'/items/'.$itemId.'/'.$decision,
            ['note' => $note, 'reviewer_name' => $reviewerName],
        );
    }

    /**
     * @throws ConnectionException
     */
    public function completeRequest(ArchivioSmartSetting $setting, string $externalId): Response
    {
        return $this->request($setting)->post($this->requestPath($externalId).'/complete');
    }

    /**
     * Asks for the temporary URL of an uploaded file (spec 0429, D-1).
     *
     * @throws ConnectionException
     */
    public function documentDownloadUrl(ArchivioSmartSetting $setting, string $externalId, int $documentId): Response
    {
        return $this->request($setting)->get($this->requestPath($externalId).'/documents/'.$documentId.'/download');
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

    private function requestPath(string $externalId): string
    {
        return '/requests/'.rawurlencode($externalId);
    }

    private function categoryPath(string $externalId): string
    {
        return '/categories/'.rawurlencode($externalId);
    }
}
