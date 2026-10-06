<?php

namespace App\Services;

use App\DataObjects\FinancialAccounts\CreateFinancialAccountData;
use App\DataObjects\FinancialAccounts\UpdateFinancialAccountData;
use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Enums\FinancialAccountType;
use App\Models\FinancialAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Business logic for the `financial-accounts` resource (spec 0189). The
 * controller stays thin; this Service owns the derived `card_last_four`, the
 * delete guard (D-10) and the audited reveal of a full card number (D-1).
 */
class FinancialAccountService
{
    /** Relations the FinancialAccountResource reads. */
    private const array RESOURCE_RELATIONS = [
        'company:id,denomination',
        'country:id,name',
        'state:id,name',
        'province:id,name',
        'city:id,name',
        'linkedAccount:id,name,iban',
    ];

    private const int LAST_FOUR = 4;

    private const string REVEAL_EVENT = 'card_number_revealed';

    public function create(CreateFinancialAccountData $data): FinancialAccount
    {
        $attributes = ['type' => $data->type, ...$data->attributes];

        $account = FinancialAccount::create($this->withLastFour($attributes));

        return $this->detail($account);
    }

    public function update(FinancialAccount $account, UpdateFinancialAccountData $data): FinancialAccount
    {
        $account->fill($this->withLastFour($data->attributes))->save();

        return $this->detail($account);
    }

    /**
     * Restrictive delete (D-10): a bank account with associated cards stays.
     */
    public function delete(FinancialAccount $account): void
    {
        if ($account->linkedCards()->exists()) {
            abort(409, 'This account has linked cards and cannot be deleted.');
        }

        $account->delete();
    }

    /**
     * The account with the relations its Resource needs eager-loaded.
     */
    public function detail(FinancialAccount $account): FinancialAccount
    {
        return $account->fresh(self::RESOURCE_RELATIONS);
    }

    /**
     * The clear card number, with an activity-log entry recording who looked.
     * 404 when the account is not a card or has no number stored.
     */
    public function revealCardNumber(FinancialAccount $account, User $actor): string
    {
        $number = $account->type === FinancialAccountType::Card ? $account->card_number : null;

        if ($number === null) {
            abort(404);
        }

        activity($account->getTable())
            ->performedOn($account)
            ->causedBy($actor)
            ->event(self::REVEAL_EVENT)
            ->log('Card number revealed');

        return $number;
    }

    /**
     * Minimal, searchable, paginated list for the for-select standard
     * (ADR 0011), optionally narrowed to one account type.
     */
    public function forSelect(ForSelectQuery $query, ?FinancialAccountType $type = null, ?int $companyId = null): ForSelectResult
    {
        $filtered = FinancialAccount::query()
            ->select(['id', 'name', 'iban', 'type'])
            ->when($type !== null, fn (Builder $builder) => $builder->where('type', $type?->value))
            ->when($companyId !== null, fn (Builder $builder) => $builder->where('company_id', $companyId))
            ->when($query->hasSearch(), fn (Builder $builder) => $builder->where('name', 'like', '%'.$query->search.'%'));

        $result = $query->page($filtered, fn (Builder $builder) => $builder->orderBy('name')->orderBy('id'));

        return $query->hasIds() ? $this->withHydratedIds($result, $query, $type) : $result;
    }

    /**
     * Append the explicitly requested `ids[]` (edit-mode hydration) missing
     * from the page: they bypass the search and do not change the total.
     */
    private function withHydratedIds(ForSelectResult $result, ForSelectQuery $query, ?FinancialAccountType $type): ForSelectResult
    {
        $missing = array_values(array_diff($query->ids, $result->items->pluck('id')->all()));

        if ($missing === []) {
            return $result;
        }

        $hydrated = FinancialAccount::query()
            ->select(['id', 'name', 'iban', 'type'])
            ->whereIn('id', $missing)
            ->when($type !== null, fn (Builder $builder) => $builder->where('type', $type?->value))
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $result->withItems($result->items->concat($hydrated));
    }

    /**
     * Derive `card_last_four` whenever a (new) card number is written.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function withLastFour(array $attributes): array
    {
        if (isset($attributes['card_number'])) {
            $attributes['card_last_four'] = substr((string) $attributes['card_number'], -self::LAST_FOUR);
        }

        return $attributes;
    }
}
