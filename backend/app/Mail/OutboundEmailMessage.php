<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\OutboundEmail;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The one Mailable this app sends through SendOutboundEmailJob (spec 0175,
 * D-1/D-6/D-7). Every field is a snapshot ALREADY resolved and persisted on
 * the OutboundEmail row by the composer/send endpoints (be-05) -- this class
 * does no resolving of its own (no template rendering, no recipient lookup):
 * it only shapes what MicrosoftGraphTransport (or `log`, in dev) needs from
 * a Laravel Mailable.
 *
 * `from` is deliberately NOT the app's `config('mail.from')`: it is
 * `from_address` (the sending user's own `users.email`, snapshotted at send
 * time), matching MicrosoftGraphTransport's `/users/{from}/messages`.
 */
final class OutboundEmailMessage extends Mailable
{
    use SerializesModels;

    public function __construct(private readonly OutboundEmail $email)
    {
        $this->email->loadMissing([
            'sender',
            'attachments' => fn ($query) => $query->where('collection', OutboundEmail::ATTACHMENT_COLLECTION),
        ]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address((string) $this->email->from_address, $this->email->sender?->name),
            to: $this->addresses($this->email->to_recipients ?? []),
            cc: $this->addresses($this->email->cc_recipients ?? []),
            bcc: $this->addresses($this->email->bcc_recipients ?? []),
            subject: (string) $this->email->subject,
        );
    }

    public function content(): Content
    {
        // The body is already sanitized (RichTextSanitizer, D-11) at every
        // save -- rendered raw here, exactly like the rich-text renderers
        // elsewhere in this app.
        return new Content(
            view: 'emails.outbound',
            with: ['body' => (string) $this->email->body],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return $this->email->attachments
            ->map(fn ($attachment) => Attachment::fromStorageDisk($attachment->disk, $attachment->path)
                ->as($attachment->original_name)
                ->withMime($attachment->mime_type))
            ->all();
    }

    /**
     * @param  array<int, string>  $recipients
     * @return array<int, Address>
     */
    private function addresses(array $recipients): array
    {
        return array_map(static fn (string $email) => new Address($email), $recipients);
    }
}
