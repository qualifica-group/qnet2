<?php

namespace App\Tables;

use App\Authorization\AuthorizationRegistry;
use App\DataObjects\Enums\EnumMeta;
use App\Enums\EnasarcoMandateType;
use App\Http\Requests\AgentFiscalProfiles\AgentFiscalProfileRequest;
use App\Models\AgentFiscalProfile;
use App\Models\Referent;
use App\Models\User;
use App\Services\AgentFiscalProfileService;
use App\Services\Table\FormRequestCellValidator;
use App\Tables\AgentFiscalProfiles\AgentFiscalProfileColumnCatalog as Catalog;
use App\Tables\AgentFiscalProfiles\AgentFiscalProfileVatRateQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `agent-fiscal-profiles` domain (spec 0212). Two
 * columns are derived: `referent` (the agent's name: sorted via a correlated
 * subquery, filtered via whereHas) and `is_current` ("valid today": yes/no
 * set filter on the validity range). Everything else is a real column.
 */
class AgentFiscalProfilesTableDefinition extends AbstractTableDefinition
{
    /** Caps the WHERE IN cardinality of the `referent` set filter. */
    private const int MAX_FILTER_VALUES = 200;

    /** Column id => the permission field that hides it (the mandatory referent_id and valid_from can never be hidden). */
    private const array COLUMN_FIELDS = [
        'withholding_enabled' => 'withholding_enabled',
        'withholding_rate' => 'withholding_rate',
        'withholding_base_percent' => 'withholding_base_percent',
        'enasarco_enabled' => 'enasarco_enabled',
        'enasarco_mandate_type' => 'enasarco_mandate_type',
        'vat_rate' => 'vat_rate_id',
        'valid_until' => 'valid_until',
    ];

    /** @var array<int, array<int, string>> */
    private array $hiddenColumns = [];

    public function __construct(
        private readonly AgentFiscalProfileService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly FormRequestCellValidator $cells,
    ) {}

    /**
     * Spec 0264, D-2: a cell edit follows the profile's own FormRequest (partial
     * PATCH merge, cross rules, overlap) and service, never the generic update.
     */
    public function updateCell(Model $row, string $columnId, mixed $value): Model
    {
        /** @var AgentFiscalProfile $row */
        /** @var User $actor */
        $actor = Auth::user();
        $request = $this->cells->validate(AgentFiscalProfileRequest::class, 'agentFiscalProfile', $row, $actor, [$columnId => $value]);

        return $this->service->update($row, $request->toData());
    }

    public function domain(): string
    {
        return 'agent-fiscal-profiles';
    }

    /**
     * @return class-string<AgentFiscalProfile>
     */
    public function modelClass(): string
    {
        return AgentFiscalProfile::class;
    }

    /**
     * @return Builder<AgentFiscalProfile>
     */
    public function baseQuery(): Builder
    {
        return AgentFiscalProfile::query()->with(['referent:id,name', 'vatRate:id,name,rate']);
    }

    /**
     * The catalogue minus the columns whose field the current user cannot see.
     * The user comes from the auth guard (set by the request and by the export
     * job): this feeds the sort/filter allow-lists, which take no actor, so a
     * hidden column cannot be probed through sort or filters (422).
     *
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        $actor = Auth::user();
        $hidden = $actor instanceof User ? $this->hiddenColumnIds($actor) : [];

        return array_values(array_filter(
            Catalog::columns(),
            static fn (array $column): bool => ! in_array($column['id'], $hidden, true),
        ));
    }

    public function filters(): array
    {
        return Catalog::filters($this->columns());
    }

    public function actions(): array
    {
        return Catalog::actions();
    }

    public function defaultSort(): array
    {
        return [
            ['columnId' => 'referent', 'direction' => 'asc'],
            ['columnId' => 'valid_from', 'direction' => 'desc'],
        ];
    }

    public function defaultPagination(): array
    {
        return ['limit' => 25];
    }

    /**
     * Frontend `enums.*` keys: cells and Set Filter checklists read the same labels.
     */
    protected function enumKeyFor(string $columnId, User $actor): ?string
    {
        return match ($columnId) {
            'enasarco_mandate_type' => 'enasarco_mandate_type',
            'is_current' => 'installment_overdue',
            default => null,
        };
    }

    protected function badgesFor(string $columnId, User $actor): ?array
    {
        return match ($columnId) {
            'enasarco_mandate_type' => array_map(static fn (EnumMeta $meta): array => $meta->toArray(), EnasarcoMandateType::options()),
            'is_current' => [
                (new EnumMeta(Catalog::CURRENT_YES, __('In force'), 'green'))->toArray(),
                (new EnumMeta(Catalog::CURRENT_NO, __('Not in force'), 'slate'))->toArray(),
            ],
            default => null,
        };
    }

    public function mapRow(User $actor, Model $row): array
    {
        /** @var AgentFiscalProfile $row */
        $payload = [
            'id' => $row->id,
            'referent_id' => $row->referent_id,
            'referent' => $row->referent?->name,
            'withholding_enabled' => $row->withholding_enabled,
            'withholding_rate' => $row->withholding_rate === null ? null : (float) $row->withholding_rate,
            'withholding_base_percent' => $row->withholding_base_percent === null ? null : (float) $row->withholding_base_percent,
            'enasarco_enabled' => $row->enasarco_enabled,
            'enasarco_mandate_type' => $row->enasarco_mandate_type?->value,
            'vat_rate_id' => $row->vat_rate_id,
            'vat_rate' => $row->vatRate === null ? null : Catalog::vatRateLabel($row->vatRate),
            'valid_from' => $row->valid_from?->format('Y-m-d'),
            'valid_until' => $row->valid_until?->format('Y-m-d'),
            'is_current' => $this->isCurrent($row) ? Catalog::CURRENT_YES : Catalog::CURRENT_NO,
            'updated_at' => $row->updated_at,
            // Spec 0264, D-2: the rates and the mandate type follow their switch.
            'editable_columns' => Catalog::rowEditableColumnIds($row->withholding_enabled, $row->enasarco_enabled),
            'abilities' => [
                'update' => Gate::forUser($actor)->allows('update', $row),
                'delete' => Gate::forUser($actor)->allows('delete', $row),
            ],
        ];

        foreach ($this->hiddenColumnIds($actor) as $column) {
            unset($payload[$column]);
        }

        // The id travels with its label: hiding the field hides both.
        if (! array_key_exists('vat_rate', $payload)) {
            unset($payload['vat_rate_id']);
        }

        return $payload;
    }

    public function actionsFor(User $actor, Model $row): array
    {
        $allowed = [];

        foreach (['view' => 'view', 'delete' => 'delete', 'viewActivity' => 'activity'] as $ability => $action) {
            if (Gate::forUser($actor)->allows($ability, $row)) {
                $allowed[] = $action;
            }
        }

        return $allowed;
    }

    public function deleteModel(Model $model): void
    {
        /** @var AgentFiscalProfile $model */
        $this->service->delete($model);
    }

    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        return match ($columnId) {
            'referent' => $this->applyReferentFilter($query, $filter),
            'is_current' => $this->applyCurrentFilter($query, $filter),
            'vat_rate' => AgentFiscalProfileVatRateQuery::applyFilter($query, $filter),
            default => false,
        };
    }

    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        if ($columnId === 'vat_rate') {
            AgentFiscalProfileVatRateQuery::applySort($query, $direction);

            return true;
        }

        if ($columnId !== 'referent') {
            return false;
        }

        $query->orderBy(
            Referent::query()->select('name')->whereColumn('referents.id', 'agent_fiscal_profiles.referent_id')->limit(1),
            $direction,
        );

        return true;
    }

    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        if (in_array($columnId, $this->hiddenColumnIds($actor), true)) {
            return [];
        }

        if ($columnId === 'is_current') {
            return [Catalog::CURRENT_YES, Catalog::CURRENT_NO];
        }

        if ($columnId === 'vat_rate') {
            return $this->withBlankEntry(
                AgentFiscalProfileVatRateQuery::labels($query, $search, $limit),
                $search,
                static fn (): bool => (clone $query)->reorder()->whereNull('agent_fiscal_profiles.vat_rate_id')->exists(),
            );
        }

        if ($columnId !== 'referent') {
            return null;
        }

        return DB::table('referents')
            ->whereIn('id', (clone $query)->select('referent_id'))
            ->when($search !== null && $search !== '', fn ($builder) => $builder->where('name', 'like', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%'))
            ->orderBy('name')
            ->limit($limit)
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();
    }

    /**
     * @param  Builder<AgentFiscalProfile>  $query
     * @param  array<string, mixed>  $filter
     */
    private function applyReferentFilter(Builder $query, array $filter): bool
    {
        $names = array_slice(array_values(array_filter(
            (array) ($filter['values'] ?? []),
            static fn ($value): bool => is_string($value) && $value !== '',
        )), 0, self::MAX_FILTER_VALUES);

        if ($names !== []) {
            $query->whereHas('referent', static fn (Builder $related) => $related->whereIn('name', $names));
        }

        return true;
    }

    /**
     * Validity range contains today: valid_from <= today and (valid_until is
     * null or >= today). Both values selected (or none) narrow nothing.
     *
     * @param  Builder<AgentFiscalProfile>  $query
     * @param  array<string, mixed>  $filter
     */
    private function applyCurrentFilter(Builder $query, array $filter): bool
    {
        $values = array_intersect([Catalog::CURRENT_YES, Catalog::CURRENT_NO], (array) ($filter['values'] ?? []));

        if (count($values) !== 1) {
            return true;
        }

        $today = now()->toDateString();
        $current = static fn (Builder $group) => $group
            ->whereDate('valid_from', '<=', $today)
            ->where(fn (Builder $until) => $until->whereNull('valid_until')->orWhereDate('valid_until', '>=', $today));

        if (reset($values) === Catalog::CURRENT_YES) {
            $query->where($current);
        } else {
            $query->whereNot($current);
        }

        return true;
    }

    private function isCurrent(AgentFiscalProfile $profile): bool
    {
        $today = now()->toDateString();

        return $profile->valid_from->toDateString() <= $today
            && ($profile->valid_until === null || $profile->valid_until->toDateString() >= $today);
    }

    /**
     * Memoized per actor: mapRow() asks for it once per row.
     *
     * @return array<int, string> the column ids whose permission field is hidden for the actor
     */
    private function hiddenColumnIds(User $actor): array
    {
        if (! isset($this->hiddenColumns[$actor->id])) {
            $permissions = $this->authorization->resolve('agent-fiscal-profiles')->fieldPermissions($actor, null);

            $this->hiddenColumns[$actor->id] = array_keys(array_filter(
                self::COLUMN_FIELDS,
                static fn (string $field): bool => ! ($permissions[$field]->visible ?? true),
            ));
        }

        return $this->hiddenColumns[$actor->id];
    }
}
