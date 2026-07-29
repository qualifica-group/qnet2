<?php

declare(strict_types=1);

namespace App\Services\Commissions;

use App\DataObjects\Commissions\AppliedCommissionDraft;
use App\DataObjects\Commissions\CommissionCalculationInput;
use App\DataObjects\Commissions\QuoteCommissionDefaultsData;
use App\DataObjects\Quotes\QuoteLineCommissionData;
use App\Enums\CommissionOrigin;
use App\Models\QuoteLine;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final class QuoteLineCommissionWriter
{
    /** @var array<string, class-string<Model>> */
    private const array RECIPIENT_MODELS = [
        'referent' => Referent::class,
        'user' => User::class,
        'registry' => Registry::class,
    ];

    /** @var array<string, string> */
    private const array ROLE_TYPES = [
        'COMMERCIAL' => 'referent',
        'REPORTER' => 'referent',
        'SUPERVISOR' => 'user',
        'SUPPLIER' => 'registry',
    ];

    public function __construct(
        private readonly QuoteCommissionInitializer $initializer,
        private readonly CommissionCalculator $calculator,
    ) {}

    /** @param array<int, QuoteLineCommissionData>|null $submitted */
    public function sync(QuoteLine $line, ?array $submitted, bool $regenerate = false): void
    {
        $defaults = $this->defaults($line);

        if ($regenerate || ($submitted === null && ! $line->commissions()->exists())) {
            $this->replaceWithDefaults($line, $defaults);

            return;
        }

        if ($submitted === null) {
            $this->recalculateAndInitializeMissing($line, $defaults);

            return;
        }

        $defaultsByRole = collect($defaults)->keyBy(fn ($draft) => $draft->role->value);
        $keptRoles = [];

        foreach ($submitted as $commission) {
            $keptRoles[] = $commission->role->value;
            $existing = $line->commissions()->where('recipient_role', $commission->role)->first();

            if ($commission->origin !== CommissionOrigin::ManualOverride) {
                $draft = $defaultsByRole->get($commission->role->value);

                if ($draft === null) {
                    $existing?->delete();

                    continue;
                }

                $this->persistDraft($line, $draft);

                continue;
            }

            $recipient = $this->validatedRecipient($commission);
            $amount = $this->calculator->calculate(new CommissionCalculationInput(
                type: $commission->type,
                value: $commission->value,
                lineNetAmount: $line->net_amount,
            ));

            $line->commissions()->updateOrCreate(
                ['recipient_role' => $commission->role],
                [
                    'commission_configuration_id' => $existing?->commission_configuration_id,
                    'recipient_type' => $recipient->getMorphClass(),
                    'recipient_id' => $recipient->getKey(),
                    'commission_type' => $commission->type,
                    'value' => $commission->value,
                    'calculated_amount' => $amount,
                    'internal_note' => $commission->internalNote,
                    'origin' => CommissionOrigin::ManualOverride,
                ],
            );
        }

        $line->commissions()->whereNotIn('recipient_role', $keptRoles)->delete();

        // The defaults endpoint is only a UI aid: persistence must repeat
        // resolution server-side. A stale or deliberately incomplete client
        // collection cannot suppress a currently applicable automatic rule.
        foreach ($defaults as $draft) {
            if (! in_array($draft->role->value, $keptRoles, true)) {
                $this->persistDraft($line, $draft);
            }
        }
    }

    /** @param array<int, AppliedCommissionDraft> $defaults */
    private function replaceWithDefaults(QuoteLine $line, array $defaults): void
    {
        $line->commissions()->delete();

        foreach ($defaults as $draft) {
            $this->persistDraft($line, $draft);
        }
    }

    /** @param array<int, AppliedCommissionDraft> $defaults */
    private function recalculateAndInitializeMissing(QuoteLine $line, array $defaults): void
    {
        $defaultsByRole = collect($defaults)->keyBy(fn ($draft) => $draft->role->value);

        foreach ($line->commissions()->get() as $commission) {
            if ($commission->origin !== CommissionOrigin::ManualOverride) {
                $default = $defaultsByRole->get($commission->recipient_role->value);

                if ($default === null) {
                    $commission->delete();

                    continue;
                }

                // Keep the persisted rule snapshot immutable, but follow the
                // Quote's current role recipient. Re-resolving the financial
                // fields here would let later configuration edits mutate an
                // already-saved Quote.
                $commission->recipient_type = $default->recipient->type;
                $commission->recipient_id = $default->recipient->id;
            }

            $commission->calculated_amount = $this->calculator->calculate(new CommissionCalculationInput(
                type: $commission->commission_type,
                value: $commission->value,
                lineNetAmount: $line->net_amount,
            ));
            $commission->save();
        }

        foreach ($defaults as $draft) {
            if (! $line->commissions()->where('recipient_role', $draft->role)->exists()) {
                $this->persistDraft($line, $draft);
            }
        }
    }

    private function persistDraft(QuoteLine $line, mixed $draft): void
    {
        $line->commissions()->updateOrCreate(
            ['recipient_role' => $draft->role],
            [
                'commission_configuration_id' => $draft->configurationId,
                'recipient_type' => $draft->recipient->type,
                'recipient_id' => $draft->recipient->id,
                'commission_type' => $draft->type,
                'value' => $draft->value,
                'calculated_amount' => $draft->calculatedAmount,
                'internal_note' => $draft->internalNote,
                'origin' => $draft->origin,
            ],
        );
    }

    /** @return array<int, AppliedCommissionDraft> */
    private function defaults(QuoteLine $line): array
    {
        return $this->initializer->initialize(new QuoteCommissionDefaultsData(
            quoteId: $line->quote_id,
            productId: $line->product_id,
            lineNetAmount: $line->net_amount,
            commercialId: null,
            reporterId: null,
            supervisorId: null,
            referenceDate: new DateTimeImmutable,
        ));
    }

    private function validatedRecipient(QuoteLineCommissionData $commission): Model
    {
        $expectedType = self::ROLE_TYPES[$commission->role->value];

        if ($commission->recipientType !== $expectedType || $commission->recipientId === null) {
            throw ValidationException::withMessages([
                'commissions' => [__('commission_configurations.invalid_recipient')],
            ]);
        }

        $model = self::RECIPIENT_MODELS[$expectedType]::find($commission->recipientId);

        if ($model === null) {
            throw ValidationException::withMessages([
                'commissions' => [__('commission_configurations.invalid_recipient')],
            ]);
        }

        return $model;
    }
}
