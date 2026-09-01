<?php

namespace App\DataObjects\Referents;

/**
 * Validated payload for a partial (PATCH) referent update
 * (PUT/PATCH /api/referents/{referent}).
 *
 * Declared DTO (no "magic flying array") so the UpdateReferentRequest ->
 * ReferentService contract is explicit — see standards/architecture.md ->
 * Data Transfer Objects.
 *
 * `referent_type_id` and `notes` are both legitimately nullable VALUES
 * (removing the type, clearing the notes), so a plain null property cannot
 * distinguish "not submitted" from "submitted as null" — the `*Submitted`
 * flags carry that distinction explicitly, mirroring
 * UpdateBusinessFunctionData's managerId/managerSubmitted.
 */
final readonly class UpdateReferentData
{
    public function __construct(
        public ?int $referentTypeId = null,
        public bool $referentTypeIdSubmitted = false,
        public ?string $contactScope = null,
        public ?string $notes = null,
        public bool $notesSubmitted = false,
        public ?int $userId = null,
        public bool $userIdSubmitted = false,
    ) {}

    /**
     * Build from the validated UpdateReferentRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            referentTypeId: array_key_exists('referent_type_id', $data) && $data['referent_type_id'] !== null
                ? (int) $data['referent_type_id']
                : null,
            referentTypeIdSubmitted: array_key_exists('referent_type_id', $data),
            contactScope: array_key_exists('contact_scope', $data) ? (string) $data['contact_scope'] : null,
            notes: array_key_exists('notes', $data) ? $data['notes'] : null,
            notesSubmitted: array_key_exists('notes', $data),
            userId: array_key_exists('user_id', $data) && $data['user_id'] !== null
                ? (int) $data['user_id']
                : null,
            userIdSubmitted: array_key_exists('user_id', $data),
        );
    }

    public function hasReferentTypeId(): bool
    {
        return $this->referentTypeIdSubmitted;
    }

    public function hasNotes(): bool
    {
        return $this->notesSubmitted;
    }

    public function hasUserId(): bool
    {
        return $this->userIdSubmitted;
    }

    /**
     * Only the attributes the client actually submitted, ready for a partial
     * mass-assignment update (framework array boundary).
     *
     * @return array<string, mixed>
     */
    public function submittedAttributes(): array
    {
        $attributes = [];

        if ($this->referentTypeIdSubmitted) {
            $attributes['referent_type_id'] = $this->referentTypeId;
        }

        if ($this->contactScope !== null) {
            $attributes['contact_scope'] = $this->contactScope;
        }

        if ($this->notesSubmitted) {
            $attributes['notes'] = $this->notes;
        }

        if ($this->userIdSubmitted) {
            $attributes['user_id'] = $this->userId;
        }

        return $attributes;
    }
}
