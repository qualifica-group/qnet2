<?php

declare(strict_types=1);

namespace App\Services\Attachments;

use App\Models\Attachment;
use App\Support\LegacyFileUrl;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reads the file of a link-only legacy document from the legacy QNet
 * (spec 0295 D-2, D-8, D-11): either as a streaming proxy response for
 * download/view, or as bytes for AttachmentService::copyTo().
 *
 * The SSRF boundary is checked before every request, redirects are never
 * followed, and no legacy URL/host ever reaches an error message. The response
 * type is always the stored `mime_type` (never the legacy one, D-12).
 */
final class LegacyAttachmentFetcher
{
    private const int CHUNK_BYTES = 8192;

    /**
     * The legacy file as an authorized proxy response. The legacy request is
     * made before returning, so its failures surface as the error envelope.
     *
     * @param  'attachment'|'inline'  $disposition
     */
    public function stream(Attachment $attachment, string $disposition): StreamedResponse
    {
        $body = $this->open($attachment)->toPsrResponse()->getBody();

        return response()->stream(function () use ($body): void {
            while (! $body->eof()) {
                echo $body->read(self::CHUNK_BYTES);
                flush();
            }
        }, 200, [
            'Content-Type' => $attachment->mime_type,
            'Content-Disposition' => $this->contentDisposition($disposition, $attachment->original_name),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * The legacy file bytes, read in chunks and refused (422) as soon as they
     * exceed $maxBytes so an oversized file is never held in memory.
     */
    public function contents(Attachment $attachment, int $maxBytes): string
    {
        $body = $this->open($attachment)->toPsrResponse()->getBody();

        return $this->readBounded($body, $maxBytes);
    }

    /**
     * Streams the legacy file into a local file, chunk by chunk, so a large
     * document never sits in memory (used by AttachmentArchiveBuilder).
     */
    public function saveTo(Attachment $attachment, string $target): void
    {
        $body = $this->open($attachment)->toPsrResponse()->getBody();
        $handle = fopen($target, 'wb');

        try {
            while (! $body->eof()) {
                fwrite($handle, $body->read(self::CHUNK_BYTES));
            }
        } finally {
            fclose($handle);
        }
    }

    private function open(Attachment $attachment): Response
    {
        $url = $attachment->external_url;

        if (! LegacyFileUrl::isAllowed($url)) {
            abort(404, 'The requested file no longer exists.');
        }

        try {
            $response = Http::withOptions(['stream' => true, 'allow_redirects' => false])
                ->timeout((int) config('migrations.legacy_files_timeout'))
                ->get($url);
        } catch (ConnectionException) {
            abort(502, 'The original document is temporarily unavailable.');
        }

        if ($response->status() === 404) {
            abort(404, 'The requested file no longer exists.');
        }

        if (! $response->successful()) {
            abort(502, 'The original document is temporarily unavailable.');
        }

        return $response;
    }

    private function readBounded(StreamInterface $body, int $maxBytes): string
    {
        $contents = '';

        while (! $body->eof()) {
            $contents .= $body->read(self::CHUNK_BYTES);

            if (strlen($contents) > $maxBytes) {
                throw ValidationException::withMessages([
                    'attachments' => [__('The document exceeds the maximum allowed size.')],
                ]);
            }
        }

        return $contents;
    }

    private function contentDisposition(string $disposition, string $name): string
    {
        $fallback = str_replace(['%', '"'], '', Str::ascii($name));

        return HeaderUtils::makeDisposition($disposition, $name, $fallback !== '' ? $fallback : 'file');
    }
}
