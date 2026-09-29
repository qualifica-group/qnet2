<?php

declare(strict_types=1);

namespace App\Mail\Transport;

use App\Services\Graph\GraphMailClient;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Component\Mime\Part\DataPart;

/**
 * Laravel/Symfony mail transport for Microsoft Graph (spec 0175, D-1),
 * registered via `Mail::extend('microsoft-graph', ...)` in
 * AppServiceProvider. Every OutboundEmail send passes through this same
 * `Mail::mailer('microsoft-graph')->send()` path -- an app-registration
 * client-credentials call to a specific mailbox (`from` = the sending
 * user's own `users.email`, D-6), not SMTP.
 *
 * Purely a MAPPING layer: converts the already-built Symfony `SentMessage`
 * (produced by OutboundEmailMessage/Mailable) into GraphMailClient's plain
 * array shapes. All Graph HTTP calls, token handling and error translation
 * live in GraphMailClient/GraphMailException.
 */
final class MicrosoftGraphTransport extends AbstractTransport
{
    public function __construct(private readonly GraphMailClient $client)
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return 'microsoft-graph';
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $from = $this->firstAddress($email->getFrom());

        $this->client->send(
            fromUserId: $from,
            toRecipients: $this->mapAddresses($email->getTo()),
            ccRecipients: $this->mapAddresses($email->getCc()),
            bccRecipients: $this->mapAddresses($email->getBcc()),
            subject: (string) $email->getSubject(),
            htmlBody: (string) ($email->getHtmlBody() ?? $email->getTextBody() ?? ''),
            attachments: $this->mapAttachments($email->getAttachments()),
        );
    }

    /**
     * @param  array<int, Address>  $addresses
     */
    private function firstAddress(array $addresses): string
    {
        return $addresses === [] ? '' : $addresses[0]->getAddress();
    }

    /**
     * @param  array<int, Address>  $addresses
     * @return array<int, array{address: string, name?: string}>
     */
    private function mapAddresses(array $addresses): array
    {
        return array_map(static fn (Address $address) => array_filter([
            'address' => $address->getAddress(),
            'name' => $address->getName() ?: null,
        ]), $addresses);
    }

    /**
     * @param  array<int, DataPart>  $parts
     * @return array<int, array{name: string, mime: string, content: string}>
     */
    private function mapAttachments(array $parts): array
    {
        return array_map(static fn (DataPart $part) => [
            'name' => $part->getFilename() ?? 'attachment',
            'mime' => $part->getContentType() ?: 'application/octet-stream',
            'content' => $part->getBody(),
        ], $parts);
    }
}
