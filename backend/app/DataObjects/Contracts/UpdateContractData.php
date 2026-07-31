<?php

declare(strict_types=1);

namespace App\DataObjects\Contracts;

/**
 * Validated payload for a partial (PATCH) contract update
 * (PUT/PATCH /api/contracts/{contract}, spec 0072, MT-02). Mirrors
 * UpdateQuoteData's `*Submitted` convention: every scalar is a legitimately
 * nullable VALUE (`renewal_date`/`expiry_date`/`payment_notes`/`comments`),
 * so a plain property cannot express "was this key actually present" —
 * only the 5 fillable columns (data_model `<fillable>`) are here;
 * `quote_id`/`accepted_at`/`validated_at`/`terminated_at`/etc never reach
 * this DTO, written exclusively by the domain services (MT-03).
 */
final readonly class UpdateContractData
{
    public function __construct(
        public ?int $contractStatusId = null,
        public bool $contractStatusIdSubmitted = false,
        public ?string $renewalDate = null,
        public bool $renewalDateSubmitted = false,
        public ?string $expiryDate = null,
        public bool $expiryDateSubmitted = false,
        public ?string $paymentNotes = null,
        public bool $paymentNotesSubmitted = false,
        public ?string $comments = null,
        public bool $commentsSubmitted = false,
    ) {}

    /**
     * Build from the validated UpdateContractRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            contractStatusId: array_key_exists('contract_status_id', $data) ? (int) $data['contract_status_id'] : null,
            contractStatusIdSubmitted: array_key_exists('contract_status_id', $data),
            renewalDate: array_key_exists('renewal_date', $data) ? $data['renewal_date'] : null,
            renewalDateSubmitted: array_key_exists('renewal_date', $data),
            expiryDate: array_key_exists('expiry_date', $data) ? $data['expiry_date'] : null,
            expiryDateSubmitted: array_key_exists('expiry_date', $data),
            paymentNotes: array_key_exists('payment_notes', $data) ? $data['payment_notes'] : null,
            paymentNotesSubmitted: array_key_exists('payment_notes', $data),
            comments: array_key_exists('comments', $data) ? $data['comments'] : null,
            commentsSubmitted: array_key_exists('comments', $data),
        );
    }

    /**
     * Only the attributes the client actually submitted, ready for a partial
     * mass-assignment update.
     *
     * @return array<string, mixed>
     */
    public function submittedAttributes(): array
    {
        $attributes = [];

        if ($this->contractStatusIdSubmitted) {
            $attributes['contract_status_id'] = $this->contractStatusId;
        }

        if ($this->renewalDateSubmitted) {
            $attributes['renewal_date'] = $this->renewalDate;
        }

        if ($this->expiryDateSubmitted) {
            $attributes['expiry_date'] = $this->expiryDate;
        }

        if ($this->paymentNotesSubmitted) {
            $attributes['payment_notes'] = $this->paymentNotes;
        }

        if ($this->commentsSubmitted) {
            $attributes['comments'] = $this->comments;
        }

        return $attributes;
    }
}
