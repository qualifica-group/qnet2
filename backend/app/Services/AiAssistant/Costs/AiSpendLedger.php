<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Costs;

use App\Models\AiConversation;
use App\Models\AiCostEntry;
use App\Models\AiCostReservation;
use App\Models\AiMessage;
use App\Models\AiSpendCounter;
use App\Services\AiAssistant\Settings\AiAssistantSettingsService;
use App\Services\AiAssistant\Settings\AiEffectiveSettings;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cost ledger and monthly caps of the assistant (spec 0433, D-7 and D-10). The turn calls, around
 * every model call: `reserve()` before, then exactly one of `settle()` (the response, or a failure
 * that carries a cost) or `release()` (a failure without cost).
 *
 * The cap holds under concurrent turns because `reserve()` runs in one transaction that locks the
 * month counter rows (person, then company total, always in that id order) before it sums
 * spent + open reservations. MySQL: `SELECT ... FOR UPDATE` on the two rows. SQLite (tests): the
 * transaction opens with a write, so writers are serialized on the whole database file.
 */
class AiSpendLedger
{
    public const string SIGNAL_COST_ABOVE_RESERVATION = 'cost_above_reservation';

    public const string SIGNAL_COST_MISSING = 'cost_missing';

    public const string SIGNAL_RESERVATION_EXPIRED = 'reservation_expired';

    private const string EXPIRED_MODEL_ID = 'unknown';

    public function __construct(private readonly AiAssistantSettingsService $settings) {}

    /**
     * Reserve the guaranteed maximum cost of one model call for the turn started by $turnMessage.
     *
     * @param  int  $requestBytes  UTF-8 bytes of the messages and tools sent
     * @param  int  $maxTokens  `max_tokens` of the call (reasoning limited under it)
     *
     * @throws AiSpendLimitReached when spent + reserved + this reservation would pass a cap
     */
    public function reserve(AiMessage $turnMessage, int $requestBytes, int $maxTokens, AiPriceCeiling $prices): AiReservation
    {
        $userId = (int) AiConversation::query()->whereKey($turnMessage->ai_conversation_id)->value('user_id');
        $amount = $prices->upperBoundUsd($requestBytes, $maxTokens, (int) config('ai-assistant.costs.input_margin_tokens'));
        $caps = $this->settings->effective();
        $period = now()->format('Y-m');

        $reservation = DB::transaction(function () use ($userId, $amount, $caps, $period, $turnMessage): AiCostReservation {
            // Step 1: lock the counters; the first statement is a write so SQLite serializes here
            $locked = $this->lockCounters($period, $userId);
            // Step 2: book at the reserved amount the reservations of workers that never came back
            $this->expireStale();
            // Step 3: spent + reserved + new against each cap
            $this->assertRoom($period, $userId, $amount, $caps, $locked);

            return AiCostReservation::query()->create([
                'period' => $period, 'user_id' => $userId, 'amount_usd' => $amount,
                'conversation_id' => $turnMessage->ai_conversation_id,
                'business_function_ids' => array_map('intval', $turnMessage->business_function_ids ?? []),
            ]);
        });

        return new AiReservation(
            $reservation->id, $period, $userId, $turnMessage->ai_conversation_id,
            array_map('intval', $turnMessage->business_function_ids ?? []), $amount,
        );
    }

    /**
     * Replace the reservation with the real cost: ledger row, equal shares per business function,
     * counters. A cost above the reservation is recorded as is and flagged.
     */
    public function settle(AiReservation $reservation, AiCallUsage $usage): AiSettlement
    {
        $reservation->close();
        $cost = $usage->costUsd ?? $reservation->amountUsd;
        $signal = match (true) {
            $usage->costUsd === null => self::SIGNAL_COST_MISSING,
            AiMoney::compare($cost, $reservation->amountUsd) > 0 => self::SIGNAL_COST_ABOVE_RESERVATION,
            default => null,
        };
        $caps = $this->settings->effective();

        [$entry, $spent] = DB::transaction(function () use ($reservation, $usage, $cost, $signal): array {
            $locked = $this->lockCounters($reservation->period, $reservation->userId);
            AiCostReservation::query()->whereKey($reservation->id)->delete();

            return $this->book($reservation, $usage, $cost, $signal, $locked);
        });

        if ($signal !== null) {
            Log::warning('AI assistant cost anomaly', ['signal' => $signal, 'entry_id' => $entry->id]);
        }

        return new AiSettlement($entry, $this->exceededScope($reservation->userId, $spent, $caps));
    }

    /**
     * Add $cost to the locked counters and write the ledger row with its shares.
     *
     * @param  Collection<int, AiSpendCounter>  $locked
     * @return array{0: AiCostEntry, 1: array<int, string>} the entry and the spent amount per counter user id
     */
    private function book(AiReservation $reservation, AiCallUsage $usage, string $cost, ?string $signal, Collection $locked): array
    {
        $spent = [];
        foreach ($locked as $counter) {
            $counter->spent_usd = AiMoney::add($counter->spent_usd, $cost);
            $counter->save();
            $spent[$counter->user_id] = $counter->spent_usd;
        }

        return [$this->writeEntry($reservation, $usage, $cost, $signal), $spent];
    }

    /**
     * A reservation past the TTL belongs to a worker that died after the call: the provider may
     * have billed it, so it is closed at the reserved amount (an upper bound) as a cost row with
     * a fixed signal, never dropped. A real settle arriving later is booked on top (prudent: the
     * cap only ever sees more spend, never less). Runs inside the reserving transaction.
     */
    private function expireStale(): void
    {
        $stale = AiCostReservation::query()
            ->where('created_at', '<', now()->subMinutes((int) config('ai-assistant.costs.reservation_ttl_minutes')))
            ->orderBy('id')->get();

        foreach ($stale as $row) {
            $locked = $this->lockCounters($row->period, $row->user_id);
            // Only the transaction that deletes the row books it
            if (AiCostReservation::query()->whereKey($row->id)->delete() === 0) {
                continue;
            }
            $reservation = new AiReservation(
                $row->id, $row->period, $row->user_id, $row->conversation_id,
                array_map('intval', $row->business_function_ids ?? []), $row->amount_usd,
            );
            $usage = new AiCallUsage(self::EXPIRED_MODEL_ID, null, 0, 0, 0, 0, null);
            $entry = $this->book($reservation, $usage, $row->amount_usd, self::SIGNAL_RESERVATION_EXPIRED, $locked)[0];
            Log::warning('AI assistant cost anomaly', ['signal' => self::SIGNAL_RESERVATION_EXPIRED, 'entry_id' => $entry->id]);
        }
    }

    /** Free the reservation of a call that failed without a cost. */
    public function release(AiReservation $reservation): void
    {
        $reservation->close();
        AiCostReservation::query()->whereKey($reservation->id)->delete();
    }

    /**
     * Counter rows of the person and the company total, created if missing, locked, total first.
     *
     * @return Collection<int, AiSpendCounter>
     */
    private function lockCounters(string $period, int $userId): Collection
    {
        $ids = [AiSpendCounter::TOTAL_USER_ID, $userId];
        AiSpendCounter::query()->insertOrIgnore(array_map(
            fn (int $id): array => ['period' => $period, 'user_id' => $id, 'spent_usd' => '0', 'created_at' => now(), 'updated_at' => now()],
            $ids,
        ));

        return AiSpendCounter::query()->where('period', $period)->whereIn('user_id', $ids)
            ->orderBy('user_id')->lockForUpdate()->get()->keyBy('user_id');
    }

    /**
     * @param  Collection<int, AiSpendCounter>  $locked
     */
    private function assertRoom(string $period, int $userId, string $amount, AiEffectiveSettings $caps, Collection $locked): void
    {
        $open = AiCostReservation::query()->where('period', $period)->get(['user_id', 'amount_usd']);

        foreach ($this->limits($userId, $caps) as $scope => [$counterUser, $cap]) {
            if ($cap === null) {
                continue;
            }
            $reserved = $open
                ->filter(fn (AiCostReservation $r): bool => $counterUser === AiSpendCounter::TOTAL_USER_ID || $r->user_id === $counterUser)
                ->reduce(fn (string $sum, AiCostReservation $r): string => AiMoney::add($sum, $r->amount_usd), '0');
            $needed = AiMoney::add(AiMoney::add($locked[$counterUser]->spent_usd, $reserved), $amount);
            if (AiMoney::compare($needed, AiMoney::from($cap)) > 0) {
                throw new AiSpendLimitReached($scope);
            }
        }
    }

    private function writeEntry(AiReservation $reservation, AiCallUsage $usage, string $cost, ?string $signal): AiCostEntry
    {
        $entry = AiCostEntry::query()->create([
            'user_id' => $reservation->userId,
            'conversation_id' => $reservation->conversationId,
            'model_id' => $usage->modelId,
            'provider' => $usage->provider,
            'input_tokens' => $usage->inputTokens,
            'output_tokens' => $usage->outputTokens,
            'cached_tokens' => $usage->cachedTokens,
            'reasoning_tokens' => $usage->reasoningTokens,
            'cost_usd' => $cost,
            'reserved_usd' => $reservation->amountUsd,
            'signal' => $signal,
            'incurred_on' => now()->toDateString(),
        ]);

        // "No function" is a single share with a null function id
        $functionIds = $reservation->functionIds === [] ? [null] : $reservation->functionIds;
        foreach (AiMoney::split($cost, count($functionIds)) as $i => $share) {
            $entry->shares()->create(['business_function_id' => $functionIds[$i], 'cost_usd' => $share]);
        }

        return $entry;
    }

    /**
     * The two caps with the counter each one is measured on.
     *
     * @return array<string, array{0: int, 1: string|null}>
     */
    private function limits(int $userId, AiEffectiveSettings $caps): array
    {
        return [
            AiSpendLimitReached::SCOPE_PERSON => [$userId, $caps->spendCapPersonUsd],
            AiSpendLimitReached::SCOPE_TOTAL => [AiSpendCounter::TOTAL_USER_ID, $caps->spendCapTotalUsd],
        ];
    }

    /**
     * @param  array<int, string>  $spent  spent amount per counter user id, after this settlement
     */
    private function exceededScope(int $userId, array $spent, AiEffectiveSettings $caps): ?string
    {
        foreach ($this->limits($userId, $caps) as $scope => [$counterUser, $cap]) {
            if ($cap !== null && AiMoney::compare($spent[$counterUser], AiMoney::from($cap)) > 0) {
                return $scope;
            }
        }

        return null;
    }
}
