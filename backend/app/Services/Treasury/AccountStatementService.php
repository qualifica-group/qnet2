<?php

declare(strict_types=1);

namespace App\Services\Treasury;

use App\DataObjects\Treasury\StatementFilters;
use App\Models\FinancialAccount;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * The single balance rule of financial accounts (spec 0355): the opening balance
 * at the account's opening date, plus every cash fact (manual movements,
 * transfers, collections, payments) dated on or after it. Used by statement,
 * export, balances and the account detail. Arithmetic in integer cents.
 */
final class AccountStatementService
{
    public function __construct(private readonly AccountStatementFacts $facts) {}

    public function openingDate(FinancialAccount $account): CarbonImmutable
    {
        return $account->opening_balance_date !== null
            ? CarbonImmutable::parse($account->opening_balance_date->toDateString())
            : $this->defaultOpeningDate();
    }

    public function defaultOpeningDate(): CarbonImmutable
    {
        return CarbonImmutable::parse((string) config('treasury.default_opening_date'));
    }

    /** Balance at the end of `$date` (never before the opening). */
    public function balanceAt(FinancialAccount $account, CarbonImmutable $date): string
    {
        return AccountStatementFacts::toDecimal($this->balanceCentsAt($account, $date));
    }

    /**
     * @return array{date: string, accounts: array<int, array<string, mixed>>, total: string}
     */
    public function balances(?int $companyId, CarbonImmutable $date): array
    {
        $accounts = FinancialAccount::query()->with('company:id,denomination')
            ->when($companyId !== null, fn ($query) => $query->where('company_id', $companyId))
            ->orderBy('name')->orderBy('id')->get();

        $total = 0;
        $rows = [];
        foreach ($accounts as $account) {
            $cents = $this->balanceCentsAt($account, $date);
            $total += $cents;
            $rows[] = [...$this->accountSummary($account), 'balance' => AccountStatementFacts::toDecimal($cents)];
        }

        return ['date' => $date->toDateString(), 'accounts' => $rows, 'total' => AccountStatementFacts::toDecimal($total)];
    }

    /**
     * @return array<string, mixed>
     */
    public function statement(FinancialAccount $account, StatementFilters $filters): array
    {
        $account->loadMissing('company:id,denomination');
        $opening = $this->openingDate($account);

        // Step 1: every fact from the opening to the end of the period, in order
        $facts = $this->ordered($this->facts->forAccount($account->id, $opening->toDateString(), $filters->dateTo->toDateString()));

        // Step 2: balance before the period, and the unfiltered rows of the period with the running balance
        $running = AccountStatementFacts::toCents((string) $account->opening_balance);
        $periodOpening = $running;
        $period = [];
        foreach ($facts as $fact) {
            if ($fact['date'] < $filters->dateFrom->toDateString()) {
                $running += $fact['cents'];
                $periodOpening = $running;

                continue;
            }
            $running += $fact['cents'];
            $period[] = [...$fact, 'balance_cents' => $running];
        }
        $this->assertWithinMaxRows(count($period));

        // Step 3: filters narrow the rows only; the balance of each row stays the real one
        $rows = array_values(array_filter($period, fn (array $row): bool => $this->matches($row, $filters)));

        // Step 4: totals of the shown rows, closing and today balances
        return [
            'account' => $this->accountSummary($account),
            'opening' => ['date' => $opening->toDateString(), 'balance' => AccountStatementFacts::toDecimal(AccountStatementFacts::toCents((string) $account->opening_balance))],
            'period_opening_balance' => AccountStatementFacts::toDecimal($periodOpening),
            'rows' => array_map($this->presentRow(...), $rows),
            'totals' => $this->totals($rows),
            'closing_balance' => AccountStatementFacts::toDecimal($running),
            'balance_today' => $this->balanceAt($account, CarbonImmutable::today()),
            'filtered' => $filters->isFiltered(),
        ];
    }

    /**
     * Collected and paid installments with no account (D-4), newest first.
     *
     * @return array{count: int, rows: array<int, array<string, mixed>>}
     */
    public function unassigned(?int $companyId): array
    {
        $facts = $this->facts->unassigned($this->defaultOpeningDate()->toDateString(), $companyId);
        usort($facts, fn (array $a, array $b): int => [$b['date'], $b['id']] <=> [$a['date'], $a['id']]);

        $rows = array_map(fn (array $fact): array => [
            'source' => $fact['source'],
            'installment_id' => $fact['id'],
            'document' => $fact['document'],
            'registry' => $fact['registry'],
            'date' => $fact['date'],
            'amount' => AccountStatementFacts::toDecimal(abs($fact['cents'])),
        ], $facts);

        return ['count' => count($rows), 'rows' => $rows];
    }

    private function balanceCentsAt(FinancialAccount $account, CarbonImmutable $date): int
    {
        $cents = AccountStatementFacts::toCents((string) $account->opening_balance);
        $opening = $this->openingDate($account);
        if ($date->lt($opening)) {
            return $cents;
        }

        foreach ($this->facts->forAccount($account->id, $opening->toDateString(), $date->toDateString()) as $fact) {
            $cents += $fact['cents'];
        }

        return $cents;
    }

    /**
     * @param  array<int, array<string, mixed>>  $facts
     * @return array<int, array<string, mixed>>
     */
    private function ordered(array $facts): array
    {
        usort($facts, fn (array $a, array $b): int => [$a['date'], AccountStatementFacts::SOURCE_ORDER[$a['source']], $a['id']]
            <=> [$b['date'], AccountStatementFacts::SOURCE_ORDER[$b['source']], $b['id']]);

        return $facts;
    }

    private function assertWithinMaxRows(int $count): void
    {
        if ($count > (int) config('treasury.statement_max_rows')) {
            throw ValidationException::withMessages(['date_from' => [__('treasury_statements.statement_too_many_rows')]]);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function matches(array $row, StatementFilters $filters): bool
    {
        if ($filters->types !== [] && ! in_array($row['type'], $filters->types, true)) {
            return false;
        }
        if ($filters->registryId !== null && ($row['registry']['id'] ?? null) !== $filters->registryId) {
            return false;
        }
        if ($filters->search === null) {
            return true;
        }

        $haystack = mb_strtolower($row['description'].' '.($row['document']['number'] ?? ''));

        return str_contains($haystack, mb_strtolower($filters->search));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function presentRow(array $row): array
    {
        return [
            'key' => "{$row['source']}:{$row['id']}",
            'date' => $row['date'],
            'source' => $row['source'],
            'type' => $row['type'],
            'description' => $row['description'],
            'registry' => $row['registry'],
            'document' => $row['document'],
            'work_order' => $row['work_order'],
            'inflow' => $row['cents'] > 0 ? AccountStatementFacts::toDecimal($row['cents']) : null,
            'outflow' => $row['cents'] < 0 ? AccountStatementFacts::toDecimal(-$row['cents']) : null,
            'balance' => AccountStatementFacts::toDecimal($row['balance_cents']),
            'movement_id' => $row['movement_id'],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{inflow: string, outflow: string}
     */
    private function totals(array $rows): array
    {
        $inflow = 0;
        $outflow = 0;
        foreach ($rows as $row) {
            $row['cents'] > 0 ? $inflow += $row['cents'] : $outflow -= $row['cents'];
        }

        return ['inflow' => AccountStatementFacts::toDecimal($inflow), 'outflow' => AccountStatementFacts::toDecimal($outflow)];
    }

    /**
     * @return array{id: int, name: string, type: string, company: array{id: int, name: string}|null}
     */
    private function accountSummary(FinancialAccount $account): array
    {
        return [
            'id' => $account->id,
            'name' => $account->name,
            'type' => $account->type->value,
            'company' => $account->company === null ? null : ['id' => $account->company->id, 'name' => $account->company->denomination],
        ];
    }
}
