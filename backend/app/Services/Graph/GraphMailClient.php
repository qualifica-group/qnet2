<?php

declare(strict_types=1);

namespace App\Services\Graph;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Talks to Microsoft Graph on behalf of MicrosoftGraphTransport (spec 0175,
 * D-1): acquire a client-credentials token (cached), create a draft message
 * on the FROM mailbox, attach every file (inline under 3 MB, chunked upload
 * session above), then send. Mirrors the legacy
 * `App\Services\GraphLargeAttachmentMailer` (`/Users/Repository/qnet`) --
 * same three-call shape, same chunk size -- ported onto Laravel's HTTP
 * client and this app's translated exception.
 *
 * Pure HTTP orchestration: no knowledge of Symfony's Email/DataPart, no
 * knowledge of OutboundEmail -- MicrosoftGraphTransport does that mapping,
 * so this class is reusable/testable on its own plain array shapes.
 */
final class GraphMailClient
{
    /**
     * Graph requires upload-session chunks to be a multiple of 320 KiB;
     * 3.2 MB (10 * 320 KiB) matches the legacy mailer.
     */
    private const int CHUNK_SIZE = 3276800;

    /**
     * Below this, an attachment goes in one inline POST; at or above it, a
     * chunked upload session is used instead (Graph's own inline limit).
     */
    private const int INLINE_ATTACHMENT_THRESHOLD = 3 * 1024 * 1024;

    public function __construct(
        private readonly string $tenant,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $baseUrl,
        private readonly string $authUrl,
        private readonly int $timeout,
    ) {}

    /**
     * @param  array<int, array{address: string, name?: string}>  $toRecipients
     * @param  array<int, array{address: string, name?: string}>  $ccRecipients
     * @param  array<int, array{address: string, name?: string}>  $bccRecipients
     * @param  array<int, array{name: string, mime: string, content: string}>  $attachments
     *
     * @throws GraphMailException
     */
    public function send(
        string $fromUserId,
        array $toRecipients,
        array $ccRecipients,
        array $bccRecipients,
        string $subject,
        string $htmlBody,
        array $attachments,
    ): void {
        $token = $this->accessToken();

        // Step 1: create the draft with subject/body/recipients.
        $messageId = $this->createDraft($token, $fromUserId, $toRecipients, $ccRecipients, $bccRecipients, $subject, $htmlBody);

        // Step 2: attach every file (inline or chunked per size).
        foreach ($attachments as $attachment) {
            $this->addAttachment($token, $fromUserId, $messageId, $attachment);
        }

        // Step 3: send the now-complete draft.
        $this->sendDraft($token, $fromUserId, $messageId);
    }

    /**
     * Client-credentials token, cached with a TTL comfortably below Graph's
     * own `expires_in` (never the raw expiry, in case Graph returns an
     * unusually short-lived token) so an in-flight request never starts with
     * an about-to-expire token. Cache key includes tenant+client: this
     * client's config never collides with another tenant/app registration
     * sharing the same cache store. The token itself is NEVER logged.
     */
    private function accessToken(): string
    {
        $cacheKey = "msgraph_token:{$this->tenant}:{$this->clientId}";

        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::asForm()
            ->timeout($this->timeout)
            ->post("{$this->authUrl}/{$this->tenant}/oauth2/v2.0/token", [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope' => 'https://graph.microsoft.com/.default',
                'grant_type' => 'client_credentials',
            ]);

        if (! $response->successful()) {
            throw GraphMailException::authenticationFailed();
        }

        $token = (string) $response->json('access_token');
        $expiresIn = (int) $response->json('expires_in', 3600);
        $ttl = max(60, $expiresIn - 300);

        Cache::put($cacheKey, $token, $ttl);

        return $token;
    }

    /**
     * @param  array<int, array{address: string, name?: string}>  $to
     * @param  array<int, array{address: string, name?: string}>  $cc
     * @param  array<int, array{address: string, name?: string}>  $bcc
     */
    private function createDraft(string $token, string $fromUserId, array $to, array $cc, array $bcc, string $subject, string $htmlBody): string
    {
        $payload = [
            'subject' => $subject,
            'body' => ['contentType' => 'HTML', 'content' => $htmlBody],
            'toRecipients' => $this->mapRecipients($to),
        ];

        if ($cc !== []) {
            $payload['ccRecipients'] = $this->mapRecipients($cc);
        }

        if ($bcc !== []) {
            $payload['bccRecipients'] = $this->mapRecipients($bcc);
        }

        $response = Http::withToken($token)
            ->timeout($this->timeout)
            ->post("{$this->baseUrl}/users/{$fromUserId}/messages", $payload);

        if (! $response->successful()) {
            $this->throwGraphError($response, $fromUserId);
        }

        return (string) $response->json('id');
    }

    /**
     * @param  array{name: string, mime: string, content: string}  $attachment
     */
    private function addAttachment(string $token, string $fromUserId, string $messageId, array $attachment): void
    {
        $size = strlen($attachment['content']);

        if ($size < self::INLINE_ATTACHMENT_THRESHOLD) {
            $this->uploadInlineAttachment($token, $fromUserId, $messageId, $attachment);

            return;
        }

        $this->uploadChunkedAttachment($token, $fromUserId, $messageId, $attachment, $size);
    }

    /**
     * @param  array{name: string, mime: string, content: string}  $attachment
     */
    private function uploadInlineAttachment(string $token, string $fromUserId, string $messageId, array $attachment): void
    {
        $response = Http::withToken($token)
            ->timeout($this->timeout)
            ->post("{$this->baseUrl}/users/{$fromUserId}/messages/{$messageId}/attachments", [
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'name' => $attachment['name'],
                'contentType' => $attachment['mime'],
                'contentBytes' => base64_encode($attachment['content']),
            ]);

        if (! $response->successful()) {
            $this->throwGraphError($response, $fromUserId);
        }
    }

    /**
     * @param  array{name: string, mime: string, content: string}  $attachment
     */
    private function uploadChunkedAttachment(string $token, string $fromUserId, string $messageId, array $attachment, int $size): void
    {
        $session = Http::withToken($token)
            ->timeout($this->timeout)
            ->post("{$this->baseUrl}/users/{$fromUserId}/messages/{$messageId}/attachments/createUploadSession", [
                'AttachmentItem' => [
                    'attachmentType' => 'file',
                    'name' => $attachment['name'],
                    'size' => $size,
                ],
            ]);

        if (! $session->successful()) {
            $this->throwGraphError($session, $fromUserId);
        }

        $uploadUrl = (string) $session->json('uploadUrl');
        $offset = 0;

        // The upload URL is a pre-signed SAS link: no bearer token on the PUTs.
        while ($offset < $size) {
            $chunk = substr($attachment['content'], $offset, self::CHUNK_SIZE);
            $chunkLength = strlen($chunk);
            $end = $offset + $chunkLength - 1;

            $response = Http::withHeaders([
                'Content-Length' => (string) $chunkLength,
                'Content-Range' => "bytes {$offset}-{$end}/{$size}",
                'Content-Type' => 'application/octet-stream',
            ])
                ->timeout($this->timeout)
                ->withBody($chunk, 'application/octet-stream')
                ->put($uploadUrl);

            if (! $response->successful() && $response->status() !== 201) {
                $this->throwGraphError($response, $fromUserId);
            }

            $offset += $chunkLength;
        }
    }

    private function sendDraft(string $token, string $fromUserId, string $messageId): void
    {
        $response = Http::withToken($token)
            ->timeout($this->timeout)
            ->post("{$this->baseUrl}/users/{$fromUserId}/messages/{$messageId}/send");

        if (! $response->successful()) {
            $this->throwGraphError($response, $fromUserId);
        }
    }

    /**
     * @param  array<int, array{address: string, name?: string}>  $recipients
     * @return array<int, array{emailAddress: array<string, string>}>
     */
    private function mapRecipients(array $recipients): array
    {
        return array_map(static fn (array $recipient) => [
            'emailAddress' => array_filter([
                'address' => $recipient['address'],
                'name' => $recipient['name'] ?? null,
            ]),
        ], $recipients);
    }

    /**
     * @throws GraphMailException
     */
    private function throwGraphError(Response $response, string $fromUserId): never
    {
        $code = $response->json('error.code');
        $status = $response->status();

        if ($code === 'ErrorInvalidUser') {
            throw GraphMailException::invalidSender($fromUserId);
        }

        if (in_array($status, [401, 403], true)) {
            throw GraphMailException::authenticationFailed();
        }

        throw GraphMailException::fromGraphError(is_string($code) ? $code : null, $status);
    }
}
