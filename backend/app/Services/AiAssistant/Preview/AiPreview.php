<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Preview;

use App\Enums\AiPreviewOutcome;
use App\Enums\AiPreviewState;
use Carbon\CarbonImmutable;

/**
 * The preview of a write as the turn stores it and the browser reads it (spec 0433, `AiPreview`).
 * It comes ONLY from the result of `prepare_write` returned by the connector (D-19): `content` is
 * the Markdown the connector wrote, sanitised by it and kept as it is; nothing the model said
 * about the write ever lands here.
 */
final readonly class AiPreview
{
    /** `preview_id` of the connector: 12 random bytes in hexadecimal. */
    private const string ID_PATTERN = '/^[0-9a-f]{24}$/';

    public function __construct(
        public string $id,
        public bool $confirmable,
        public string $content,
        public CarbonImmutable $expiresAt,
        public AiPreviewState $state = AiPreviewState::Open,
        public ?AiPreviewOutcome $outcome = null,
    ) {}

    /**
     * The preview in the text of a `prepare_write` result, or null when the connector did not
     * return a valid one (a refusal, an error, another shape): then the result is an ordinary one.
     */
    public static function fromToolResult(string $resultText, CarbonImmutable $expiresAt): ?self
    {
        $envelope = json_decode($resultText, true);
        $connector = is_array($envelope) && is_array($envelope['connettore'] ?? null) ? $envelope['connettore'] : [];
        $data = is_array($envelope) && is_array($envelope['dati'] ?? null) ? $envelope['dati'] : [];

        $valid = ($connector['esito'] ?? null) === 'riuscita'
            && is_string($data['preview_id'] ?? null) && preg_match(self::ID_PATTERN, $data['preview_id']) === 1
            && is_bool($data['confermabile'] ?? null)
            && is_string($data['anteprima'] ?? null) && $data['anteprima'] !== '';

        return $valid ? new self($data['preview_id'], $data['confermabile'], $data['anteprima'], $expiresAt) : null;
    }

    /**
     * @param  array<string, mixed>  $stored
     */
    public static function fromArray(array $stored): self
    {
        return new self(
            (string) $stored['id'],
            (bool) $stored['confirmable'],
            (string) $stored['content'],
            CarbonImmutable::parse((string) $stored['expires_at']),
            AiPreviewState::from((string) $stored['state']),
            isset($stored['outcome']) ? AiPreviewOutcome::from((string) $stored['outcome']) : null,
        );
    }

    /**
     * @return array{id: string, confirmable: bool, content: string, expires_at: string, state: string, outcome: string|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'confirmable' => $this->confirmable,
            'content' => $this->content,
            'expires_at' => $this->expiresAt->toIso8601String(),
            'state' => $this->state->value,
            'outcome' => $this->outcome?->value,
        ];
    }

    public function isOpen(): bool
    {
        return $this->state === AiPreviewState::Open;
    }

    public function isPast(): bool
    {
        return ! $this->expiresAt->isFuture();
    }

    /**
     * The state the click or the clock gave it; the outcome is set apart, by withOutcome, when the decision is saved.
     */
    public function settled(AiPreviewOutcome $outcome): self
    {
        return new self($this->id, $this->confirmable, $this->content, $this->expiresAt, $outcome->previewState(), null);
    }

    public function withOutcome(AiPreviewOutcome $outcome): self
    {
        return new self($this->id, $this->confirmable, $this->content, $this->expiresAt, $this->state, $outcome);
    }
}
