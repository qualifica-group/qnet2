<?php

declare(strict_types=1);

namespace App\Migrations\Support;

use Carbon\CarbonImmutable;

/**
 * What the legacy record says about an offer's outcome (spec 0189 G-6/G-7):
 * the `outcome` (LegacyQuoteOutcome value) and the `contract` block (status
 * key and dates) the legacy exporter already decided. Shared by the `quotes`
 * import and the `quotes:realign-legacy-status` command.
 */
final readonly class LegacyQuoteState
{
    public function __construct(
        public string $outcome,
        public ?string $contractStatus,
        public ?CarbonImmutable $acceptedAt,
        public ?CarbonImmutable $validatedAt,
        public ?CarbonImmutable $renewalDate,
        public ?CarbonImmutable $terminatedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $record
     */
    public static function fromRecord(array $record): self
    {
        $contract = (array) ($record['contract'] ?? []);

        return new self(
            outcome: (string) ($record['outcome'] ?? LegacyQuoteOutcome::Open->value),
            contractStatus: ($contract['status'] ?? null) === null ? null : (string) $contract['status'],
            acceptedAt: self::date($contract['accepted_at'] ?? null),
            validatedAt: self::date($contract['validated_at'] ?? null),
            renewalDate: self::date($contract['renewal_date'] ?? null),
            terminatedAt: self::date($contract['terminated_at'] ?? null),
        );
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        return $value === null || $value === '' ? null : CarbonImmutable::parse((string) $value);
    }
}
