<?php

declare(strict_types=1);

namespace App\DataObjects\Contracts;

/**
 * Validated payload for POST /api/contracts/{contract}/validate (spec 0072,
 * BR-3). Both fields are optional: `validated_at` defaults to today,
 * `contract_status_id` (when omitted) leaves the current status untouched.
 */
final readonly class ValidateContractData
{
    public function __construct(
        public ?string $validatedAt = null,
        public bool $validatedAtSubmitted = false,
        public ?int $contractStatusId = null,
        public bool $contractStatusIdSubmitted = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            validatedAt: array_key_exists('validated_at', $data) ? (string) $data['validated_at'] : null,
            validatedAtSubmitted: array_key_exists('validated_at', $data),
            contractStatusId: array_key_exists('contract_status_id', $data) ? (int) $data['contract_status_id'] : null,
            contractStatusIdSubmitted: array_key_exists('contract_status_id', $data),
        );
    }
}
