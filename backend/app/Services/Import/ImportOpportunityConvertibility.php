<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\DataObjects\Import\ImportConversionReadiness;
use App\Enums\ImportRowResolution;
use App\Enums\ImportRowStatus;
use App\Models\Campaign;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Services\Opportunities\LeadOpportunityDefaultsResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Auto-convert-to-Opportunity readiness for a `leads` import run (spec 0045):
 * assessed by `POST .../confirm` (the gate, before dispatching the commit
 * job) and reported ahead of time by `GET .../summary`
 * (`conversion_readiness`) — one implementation, never duplicated. Also the
 * SINGLE place `LeadRowPersister` asks whether a given campaign derives an
 * Opportunity product line, so the confirm-time gate and the commit-time
 * per-row defense-in-depth check can never disagree.
 */
final class ImportOpportunityConvertibility
{
    /** Staged statuses that always persist a lead (never `error`/`skipped`). */
    private const array ALWAYS_CREATABLE_STATUSES = [ImportRowStatus::Valid, ImportRowStatus::Warning];

    /**
     * Per-instance memoization: a run's global_config carries a single
     * campaign_id, so every row asks the same question during commit — never
     * re-querying the Campaign per row.
     *
     * @var array<int, bool>
     */
    private array $campaignProductLineCache = [];

    public function __construct(private readonly LeadOpportunityDefaultsResolver $defaultsResolver) {}

    public function assess(ImportRun $run): ImportConversionReadiness
    {
        $globalConfig = $run->global_config ?? [];
        $globalSiteId = $this->id($globalConfig, 'operational_site_id');
        $globalOperatorId = $this->id($globalConfig, 'operator_id');
        $campaignId = $this->id($globalConfig, 'campaign_id');

        $creatableRows = $this->creatableRows($run);
        $rowsWithoutOperator = $creatableRows->filter(
            static fn (ImportRunRow $row): bool => ($row->operator_id ?? $globalOperatorId) === null,
        );
        $rowsWithoutSite = $creatableRows->filter(
            static fn (ImportRunRow $row): bool => ($row->operational_site_id ?? $globalSiteId) === null,
        );

        return new ImportConversionReadiness(
            campaignDerivesProductLine: $campaignId !== null && $this->campaignDerivesProductLine($campaignId),
            creatableRowsCount: $creatableRows->count(),
            rowsWithoutOperatorCount: $rowsWithoutOperator->count(),
            rowsWithoutOperatorNumbers: $rowsWithoutOperator->pluck('row_number')->values()->all(),
            rowsWithoutSiteCount: $rowsWithoutSite->count(),
            rowsWithoutSiteNumbers: $rowsWithoutSite->pluck('row_number')->values()->all(),
        );
    }

    /**
     * Whether the given campaign derives a non-empty Opportunity product
     * line (LeadOpportunityDefaultsResolver's shared predicate) — reused
     * both by assess() and by LeadRowPersister's per-row defense-in-depth
     * guard.
     */
    public function campaignDerivesProductLine(int $campaignId): bool
    {
        if (array_key_exists($campaignId, $this->campaignProductLineCache)) {
            return $this->campaignProductLineCache[$campaignId];
        }

        // Spec 0094, D-1/D-2: la classificazione non e' piu' una coppia di
        // colonne sulla campagna ma una collezione, sua o del progetto a cui
        // e' legata. Si carica quel che il predicato condiviso legge davvero.
        $campaign = Campaign::query()
            ->with(['productLines', 'project.productLines'])
            ->find($campaignId);

        return $this->campaignProductLineCache[$campaignId] = $campaign !== null
            && $this->defaultsResolver->campaignDerivesProductLine($campaign);
    }

    /**
     * Rows that will CREATE a lead at commit time: `valid`/`warning` (never
     * `error`/`skipped`), plus a `duplicate` row whose operator resolution
     * (spec 0036) is `create`.
     *
     * @return Collection<int, ImportRunRow>
     */
    private function creatableRows(ImportRun $run): Collection
    {
        return ImportRunRow::query()
            ->where('import_run_id', $run->id)
            ->where(function (Builder $query): void {
                $query->whereIn('status', self::ALWAYS_CREATABLE_STATUSES)
                    ->orWhere(function (Builder $duplicate): void {
                        $duplicate->where('status', ImportRowStatus::Duplicate)
                            ->where('resolution', ImportRowResolution::Create);
                    });
            })
            ->get();
    }

    /**
     * @param  array<string, mixed>  $globalConfig
     */
    private function id(array $globalConfig, string $key): ?int
    {
        $value = $globalConfig[$key] ?? null;

        return $value === null || $value === '' ? null : (int) $value;
    }
}
