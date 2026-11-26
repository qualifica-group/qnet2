<?php

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
