<?php

declare(strict_types=1);

namespace App\Services\DocumentRequests;

use App\Models\ArchivioSmartSetting;
use App\Models\DocumentRequest;
use App\Services\ArchivioSmart\ArchivioSmartClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams an uploaded file of a document request to the user (spec 0429, D-1).
 * ArchivioSmart hands out a temporary URL: it is fetched here, with no token,
 * and never stored, logged or shown to the browser. Nothing touches the disk.
 */
class DocumentRequestFileDownloader
{
    /** Bytes read from the remote stream at a time. */
    private const int CHUNK_BYTES = 8192;

    private const array ALLOWED_SCHEMES = ['http', 'https'];

    public function __construct(private readonly ArchivioSmartClient $client) {}

    /**
     * @throws DocumentDownloadException
     */
    public function download(DocumentRequest $request, int $documentId): StreamedResponse
    {
        // Step 1: the file must be in the snapshot of this request
        $document = $this->findDocument($request, $documentId);

        // Step 2: ask ArchivioSmart for the temporary URL
        $url = $this->temporaryUrl($request, $documentId);

        // Step 3: open the remote stream and relay it
        $remote = $this->open($request, $url);
        $name = is_string($document['original_name'] ?? null) && $document['original_name'] !== '' ? $document['original_name'] : 'document-'.$documentId;
        $mime = is_string($document['mime_type'] ?? null) && $document['mime_type'] !== '' ? $document['mime_type'] : 'application/octet-stream';
        $body = $remote->toPsrResponse()->getBody();

        return response()->streamDownload(function () use ($body): void {
            while (! $body->eof()) {
                echo $body->read(self::CHUNK_BYTES);
                flush();
            }
        }, $name, ['Content-Type' => $mime]);
    }

    /**
     * @return array<string, mixed>
     */
    private function findDocument(DocumentRequest $request, int $documentId): array
    {
        foreach ($request->snapshot['items'] ?? [] as $item) {
            foreach ($item['documents'] ?? [] as $document) {
                if (is_array($document) && (int) ($document['id'] ?? 0) === $documentId) {
                    return $document;
                }
            }
        }

        throw new DocumentDownloadException(404, __('archivio_smart.document_not_found'));
    }

    private function temporaryUrl(DocumentRequest $request, int $documentId): string
    {
        $setting = ArchivioSmartSetting::current();
        if ($setting === null || ! $setting->isConfigured()) {
            throw new DocumentDownloadException(502, __('archivio_smart.not_configured'));
        }

        try {
            $response = $this->client->documentDownloadUrl($setting, (string) $request->id, $documentId);
        } catch (ConnectionException) {
            $this->fail($request, null);
        }

        if ($response->status() === 404) {
            throw new DocumentDownloadException(404, __('archivio_smart.document_not_found'));
        }

        $url = $response->json('data.url');
        if (! $response->successful() || ! $this->isHttpUrl($url)) {
            $this->fail($request, $response->status());
        }

        return $url;
    }

    private function open(DocumentRequest $request, string $url): HttpResponse
    {
        try {
            $remote = Http::withOptions(['stream' => true])
                ->timeout((int) config('archivio-smart.timeout'))
                ->connectTimeout((int) config('archivio-smart.connect_timeout'))
                ->get($url);
        } catch (ConnectionException) {
            $this->fail($request, null);
        }

        if (! $remote->successful()) {
            $this->fail($request, $remote->status());
        }

        return $remote;
    }

    private function isHttpUrl(mixed $url): bool
    {
        return is_string($url)
            && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), self::ALLOWED_SCHEMES, true)
            && parse_url($url, PHP_URL_HOST) !== null;
    }

    /**
     * The log row carries ids and the HTTP status only: never the URL, the token or the user.
     */
    private function fail(DocumentRequest $request, ?int $status): never
    {
        Log::warning('ArchivioSmart document download failed.', ['document_request_id' => $request->id, 'http_status' => $status]);

        throw new DocumentDownloadException(502, __('archivio_smart.document_download_failed'));
    }
}
