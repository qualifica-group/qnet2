<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Costs;

use App\Models\AiSpendCounter;
use App\Services\AiAssistant\Settings\AiAssistantSettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aggregated costs of the assistant for a period (spec 0433, GET /costs). Reads only the cost
 * ledger, its shares and the names of people and functions: never a message or a conversation.
 * One grouped query per breakdown, whatever the number of rows.
 */
class AiCostReport
{
    public function __construct(private readonly AiAssistantSettingsService $settings) {}

    /**
     * @return array<string, mixed>
     */
    public function build(CarbonImmutable $from, CarbonImmutable $to): array
    {
        // Step 1: per day, which is also the base of the totals
        $byDay = $this->byDay($from, $to);

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'totals' => $this->totals($byDay),
            'by_day' => $byDay->map(fn (array $day): array => [
                'date' => $day['date'], 'cost_usd' => $day['cost_usd'], 'requests' => $day['requests'],
            ])->all(),
            // Step 2: the other breakdowns
            'by_function' => $this->byFunction($from, $to),
            'by_user' => $this->byUser($from, $to),
            'by_model' => $this->byModel($from, $to),
            // Step 3: the current month against the caps
            'caps' => $this->caps(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function byDay(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return $this->ledger($from, $to)
            ->groupBy('e.incurred_on')->orderBy('e.incurred_on')
            ->selectRaw('e.incurred_on, '.self::SUMS)
            ->get()
            ->map(fn (object $row): array => [
                // SQLite keeps a time part on date columns
                'date' => substr((string) $row->incurred_on, 0, 10),
                ...$this->measures($row),
                'cache_read_tokens' => (int) $row->cached_tokens,
                'reasoning_tokens' => (int) $row->reasoning_tokens,
            ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $byDay
     * @return array<string, mixed>
     */
    private function totals(Collection $byDay): array
    {
        return [
            'cost_usd' => $byDay->reduce(fn (string $sum, array $day): string => AiMoney::add($sum, $day['cost_usd']), AiMoney::from(0)),
            'requests' => (int) $byDay->sum('requests'),
            'input_tokens' => (int) $byDay->sum('input_tokens'),
            'output_tokens' => (int) $byDay->sum('output_tokens'),
            'cache_read_tokens' => (int) $byDay->sum('cache_read_tokens'),
            'reasoning_tokens' => (int) $byDay->sum('reasoning_tokens'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function byFunction(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = DB::table('ai_cost_shares as s')
            ->join('ai_cost_entries as e', 'e.id', '=', 's.ai_cost_entry_id')
            ->leftJoin('business_functions as f', 'f.id', '=', 's.business_function_id')
            ->tap(fn (Builder $query) => $this->inPeriod($query, $from, $to))
            ->groupBy('s.business_function_id', 'f.name')
            ->selectRaw('s.business_function_id, f.name, SUM(s.cost_usd) as cost_usd, COUNT(*) as requests')
            ->get()
            ->map(fn (object $row): array => [
                'business_function_id' => $row->business_function_id === null ? null : (int) $row->business_function_id,
                'name' => $row->name,
                'cost_usd' => AiMoney::from($row->cost_usd),
                'requests' => (int) $row->requests,
            ]);

        return $this->mostExpensiveFirst($rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function byUser(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = $this->ledger($from, $to)
            ->leftJoin('users as u', 'u.id', '=', 'e.user_id')
            ->groupBy('e.user_id', 'u.name')
            ->selectRaw('e.user_id, u.name, '.self::SUMS)
            ->get()
            ->map(fn (object $row): array => ['user_id' => (int) $row->user_id, 'name' => $row->name, ...$this->measures($row)]);

        return $this->mostExpensiveFirst($rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function byModel(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = $this->ledger($from, $to)
            ->groupBy('e.model_id')
            ->selectRaw('e.model_id, '.self::SUMS)
            ->get()
            ->map(fn (object $row): array => ['model_id' => $row->model_id, ...$this->measures($row)]);

        return $this->mostExpensiveFirst($rows);
    }

    /**
     * @return array<string, string|null>
     */
    private function caps(): array
    {
        $period = now()->format('Y-m');
        $effective = $this->settings->effective();
        $spent = AiSpendCounter::query()->where('period', $period)
            ->where('user_id', AiSpendCounter::TOTAL_USER_ID)->value('spent_usd');

        return [
            'month' => $period,
            'spent_total_usd' => AiMoney::from($spent ?? 0),
            'spend_cap_total_usd' => $effective->spendCapTotalUsd === null ? null : AiMoney::from($effective->spendCapTotalUsd),
            'spend_cap_person_usd' => $effective->spendCapPersonUsd === null ? null : AiMoney::from($effective->spendCapPersonUsd),
        ];
    }

    private const string SUMS = 'SUM(e.cost_usd) as cost_usd, COUNT(*) as requests, SUM(e.input_tokens) as input_tokens, '
        .'SUM(e.output_tokens) as output_tokens, SUM(e.cached_tokens) as cached_tokens, SUM(e.reasoning_tokens) as reasoning_tokens';

    /** The ledger entries of the period, aliased `e`. */
    private function ledger(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return $this->inPeriod(DB::table('ai_cost_entries as e'), $from, $to);
    }

    /** Half-open on the day after `to`: SQLite dates carry a time part, and MySQL keeps the index usable. */
    private function inPeriod(Builder $query, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return $query->where('e.incurred_on', '>=', $from->toDateString())
            ->where('e.incurred_on', '<', $to->addDay()->toDateString());
    }

    /**
     * @return array{cost_usd: string, requests: int, input_tokens: int, output_tokens: int}
     */
    private function measures(object $row): array
    {
        return [
            'cost_usd' => AiMoney::from($row->cost_usd), 'requests' => (int) $row->requests,
            'input_tokens' => (int) $row->input_tokens, 'output_tokens' => (int) $row->output_tokens,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function mostExpensiveFirst(Collection $rows): array
    {
        return $rows->sort(fn (array $a, array $b): int => AiMoney::compare($b['cost_usd'], $a['cost_usd']))->values()->all();
    }
}
