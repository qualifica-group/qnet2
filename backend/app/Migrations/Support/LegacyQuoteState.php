<?php

declare(strict_types=1);

namespace App\Migrations\Support;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * What the legacy record says about an offer's outcome (spec 0189 G-6/G-7):
 * its own `status_quotation`, the phase of its opportunity (a legacy `fases`
 * id) and whether another offer of the same opportunity is won, plus the
 * dates the contract is aligned to. Shared by the `quotes` import and the
 * `quotes:realign-legacy-status` command.
 */
final readonly class LegacyQuoteState
{
    public function __construct(
        public int $status,
        public ?int $opportunityPhase,
        public bool $wonByOtherQuote,
        public ?CarbonImmutable $acceptedAt,
        public ?CarbonImmutable $validatedAt,
        public ?CarbonImmutable $renewalDate,
        public ?CarbonImmutable $declinedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $record
     */
    public static function fromRecord(array $record): self
    {
        $phase = (int) ($record['opportunity_phase'] ?? 0);

        return new self(
            status: (int) ($record['status'] ?? 0),
            opportunityPhase: $phase > 0 ? $phase : null,
            wonByOtherQuote: (bool) ($record['won_by_other_quote'] ?? false),
            acceptedAt: self::date($record['accepted_at'] ?? null),
            validatedAt: self::date($record['validated_at'] ?? null),
            renewalDate: self::date($record['renewal_date'] ?? null),
            declinedAt: self::date($record['declined_at'] ?? null),
        );
    }

    /**
     * Blank, legacy zero dates and unparsable values carry no date.
     */
    private static function date(mixed $value): ?CarbonImmutable
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '' || str_starts_with($value, '0000')) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
