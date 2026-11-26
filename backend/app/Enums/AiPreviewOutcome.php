<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What became of the write after the preview was settled (spec 0433, `AiPreviewOutcome`).
 * The connector answer to a confirmation maps to one of these; the other cases come from the clock and the button.
 */
enum AiPreviewOutcome: string
{
    case Succeeded = 'succeeded';
    case Rejected = 'rejected';
    case Uncertain = 'uncertain';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Stale = 'stale';

    /**
     * Connector codes of a confirmation that did not write, by outcome; any other refusal is `rejected`.
     */
    private const array CODES = [
        'esito_incerto' => self::Uncertain,
        'anteprima_scaduta' => self::Expired,
        'anteprima_sostituita' => self::Expired,
        'record_cambiato' => self::Stale,
    ];

    /**
     * @param  array<string, mixed>  $envelope  the decoded answer of the connector
     */
    public static function fromEnvelope(array $envelope): self
    {
        $connector = is_array($envelope['connettore'] ?? null) ? $envelope['connettore'] : [];

        if (($connector['esito'] ?? null) === 'riuscita') {
            return self::Succeeded;
        }

        return self::CODES[(string) ($connector['codice'] ?? '')] ?? self::Rejected;
    }

    /**
     * The fixed message that tells the person what became of the write when the turn can not go on (spec 0433, `fixed_texts`).
     */
    public function note(string $locale): string
    {
        $text = match ($this) {
            self::Succeeded => 'The change was confirmed and written. The assistant could not go on with this request.',
            self::Rejected => 'The change was confirmed but QNET2 refused it: nothing was written. The assistant could not go on with this request.',
            self::Uncertain => 'The outcome of the change is not known. Check the record before you try again. The assistant could not go on with this request.',
            self::Stale => 'The record changed after the preview: nothing was written. The assistant could not go on with this request.',
            self::Expired => 'The preview expired: nothing was written. The assistant could not go on with this request.',
            self::Cancelled => 'You cancelled the change: nothing was written. The assistant could not go on with this request.',
        };

        return __($text, [], $locale);
    }

    public function previewState(): AiPreviewState
    {
        return match ($this) {
            self::Succeeded => AiPreviewState::Confirmed,
            self::Rejected => AiPreviewState::Rejected,
            self::Uncertain => AiPreviewState::Uncertain,
            self::Expired => AiPreviewState::Expired,
            self::Cancelled => AiPreviewState::Cancelled,
            self::Stale => AiPreviewState::Stale,
        };
    }
}
