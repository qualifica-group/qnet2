<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\Campaigns\CreateCampaignData;
use App\DataObjects\Campaigns\UpdateCampaignData;
use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Models\Campaign;
use App\Models\PipelineStatus;
use App\Models\Project;
use App\Services\Concerns\GeneratesSequentialCode;
use App\Services\ProductLines\ProductLineWriter;
use App\Services\Statuses\SystemStatusGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Business logic for the `campaigns` resource (spec 0023): create/update
 * (with the server-generated CMP-0001 code, BR-1), the BR-2 classification
 * derivation (forcing `pipeline_status_id` null and clearing the campaign's
 * own `product_lines` collection — spec 0094, D-1/D-2 — when linked to a
 * project), the BR-5 geo refinement (nulling out, defence in depth, whatever
 * geo level the linked project already fills — spec 0027) and the BR-3
 * budget guard, all computed inside the write transaction with a
 * pessimistic lock on the project row so two concurrent writes against the
 * same project's budget always serialize.
 */
class CampaignService
{
    use GeneratesSequentialCode;

    public function __construct(
        private readonly SystemStatusGuard $systemStatusGuard,
        private readonly ProductLineWriter $productLineWriter,
    ) {}

    private const string CODE_PREFIX = 'CMP';

    private const string CODE_TABLE = 'campaigns';

    private const string CODE_COLUMN = 'code';

    /**
     * The 4 geo levels (spec 0027, BR-5), in parent-to-child order — the
     * single source of truth for applyGeoInheritance() below.
     *
     * @var array<int, string>
     */
    private const array GEO_LEVELS = ['country_id', 'state_id', 'province_id', 'city_id'];

    /**
     * Relations eager-loaded for the detail read tree (CampaignResource):
     * the campaign's OWN classification (standalone case) plus the linked
     * project's (the read-through source when derived_from_project=true),
     * so a single query never N+1s across either branch. Spec 0094, D-1/D-2:
     * `businessFunction`/`productCategory` are REPLACED by
     * `productLines.businessFunction`/`productLines.productCategory`, on
     * both the campaign's own collection and the linked project's.
     *
     * @var array<int, string>
     */
    private const array DETAIL_RELATIONS = [
        'project.pipelineStatus',
        'project.productLines.businessFunction',
        'project.productLines.productCategory',
        'project.country',
        'project.state',
        'project.province',
        'project.city',
        'partner',
        'pipelineStatus',
        'productLines.businessFunction',
        'productLines.productCategory',
        'country',
        'state',
        'province',
        'city',
        'operationalSite.addresses.city',
    ];

    public function loadDetail(Campaign $campaign): Campaign
    {
        return $campaign->load(self::DETAIL_RELATIONS);
    }

    /**
     * Create a new campaign. A manual `code` (spec 0025, BR-1) is persisted
     * as submitted; otherwise one is generated inside the transaction with a
     * pessimistic lock. When linked, the target project is locked first and
     * the BR-3 budget guard runs before the insert.
     */
    public function create(CreateCampaignData $data): Campaign
    {
        $campaign = DB::transaction(function () use ($data): Campaign {
            $project = null;

            if ($data->isLinkedToProject()) {
                $project = $this->lockProject($data->projectId);
                $this->assertBudgetAvailable($project, $data->totalBudget, excludingCampaignId: null);
            }

            $attributes = $this->applyGeoInheritance($data->attributes(), $project);

            // spec 0039, D-3: only for a STANDALONE campaign — a linked one
            // stays null (BR-2 derivation, read through the project instead).
            // An omitted/null FK falls back to the mandatory system_key='new'
            // status (resolved by system_key, never by name).
            if (! $data->isLinkedToProject() && $attributes['pipeline_status_id'] === null) {
                $attributes['pipeline_status_id'] = $this->systemStatusGuard->resolveNewStatusId(PipelineStatus::class);
            }

            // `code` is deliberately absent from Campaign's #[Fillable] (BR-1),
            // so a mass-assigned Campaign::create() would silently drop it,
            // leaving the NOT NULL `code` column unset — assign it directly
            // (bypasses mass-assignment guarding, mirroring how
            // CampaignFactory itself sets it) AFTER the fillable attributes.
            $campaign = new Campaign($attributes);
            $campaign->code = $data->code ?? $this->nextSequentialCode(self::CODE_TABLE, self::CODE_COLUMN, self::CODE_PREFIX);
            $campaign->save();

            // Spec 0094, D-1/D-2: `product_lines` is a to-many collection,
            // null when linked (BR-2 — the classification is derived from
            // the project, StoreCampaignRequest's `prohibited` rule keeps it
            // that way), required (min 1) when standalone.
            if ($data->productLines !== null) {
                $this->productLineWriter->sync($campaign, $data->productLines);
            }

            return $campaign;
        });

        return $this->loadDetail($campaign);
    }

    /**
     * The next sequential code (CMP-0001...) as a non-binding suggestion for
     * the create form's auto-fill (spec 0025). Lock-free: the binding value is
     * still resolved atomically in create().
     */
    public function previewNextCode(): string
    {
        return $this->peekNextSequentialCode(self::CODE_TABLE, self::CODE_COLUMN, self::CODE_PREFIX);
    }

    /**
     * Update an existing campaign. Only keys present in $data are touched
     * (partial PATCH), except `pipeline_status_id` and the geo levels the
     * EFFECTIVE project fills (BR-5), which are additionally forced by
     * resolveUpdateAttributes() whenever the EFFECTIVE project_id (after
     * this update) is non-null — `product_lines` (spec 0094) follows the
     * SAME rule but, being a to-many collection, is synced separately below,
     * never through this mass-assignment attributes array.
     */
    public function update(Campaign $campaign, UpdateCampaignData $data): Campaign
    {
        DB::transaction(function () use ($campaign, $data): void {
            $submitted = $data->submittedAttributes();
            $effectiveProjectId = array_key_exists('project_id', $submitted)
                ? $submitted['project_id']
                : $campaign->project_id;

            $project = $effectiveProjectId !== null ? $this->lockProject($effectiveProjectId) : null;

            if ($project !== null) {
                $requestedBudget = array_key_exists('total_budget', $submitted)
                    ? $submitted['total_budget']
                    : ($campaign->total_budget !== null ? (float) $campaign->total_budget : null);

                $this->assertBudgetAvailable($project, $requestedBudget, excludingCampaignId: $campaign->id);
            }

            $attributes = $this->resolveUpdateAttributes($submitted, $project);

            // Unconditional save: fire the model's saved event even when no
            // native attribute changed, so the HasCustomFields write pipeline
            // (spec 0021) persists a custom-fields-only edit.
            $campaign->fill($attributes)->save();

            // Spec 0094, D-1/D-2: a campaign EFFECTIVELY linked after this
            // update never owns product lines of its own — cleared
            // unconditionally (defence in depth, mirroring the former
            // unconditional null-forcing on the two scalar columns; the
            // FormRequest's `prohibited` rule keeps `product_lines` out of
            // $data in this branch already). A STANDALONE campaign only
            // resyncs when the collection was actually submitted (partial
            // PATCH, `sometimes`).
            if ($project !== null) {
                $this->productLineWriter->sync($campaign, []);
            } elseif ($data->hasProductLines()) {
                $this->productLineWriter->sync($campaign, $data->productLines);
            }
        });

        return $this->loadDetail($campaign);
    }

    /**
     * Restrictive delete (spec 0024 BR-2/D-4): a campaign referenced by at
     * least one lead cannot be removed, mirroring ProjectService::delete.
     */
    public function delete(Campaign $campaign): void
    {
        if ($campaign->leads()->exists()) {
            abort(409, 'This campaign has leads and cannot be deleted.');
        }

        $campaign->delete();
    }

    /**
     * Minimal, searchable, paginated campaign list for the for-select
     * standard (ADR 0011, spec 0024), mirroring SourceService::forSelect.
     * Not consumed inside this module itself (Campaigns have no for-select
     * field of their own): it feeds the Lead form's campaign field.
     */
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = $this->forSelectBaseQuery();

        if ($query->hasSearch()) {
            $base->where(function ($relatedQuery) use ($query): void {
                $relatedQuery->where('name', 'like', '%'.$query->search.'%')
                    ->orWhere('code', 'like', '%'.$query->search.'%');
            });
        }

        $total = (clone $base)->count();

        /** @var Collection<int, Campaign> $page */
        $page = $base->orderBy('name')
            ->orderBy('id')
            ->offset($query->offset)
            ->limit($query->limit)
            ->get();

        $items = $this->appendHydratedIds($page, $query);

        return new ForSelectResult(
            items: $items,
            total: $total,
            offset: $query->offset,
            limit: $query->limit,
        );
    }

    /**
     * The for-select projection query (spec 0024, ADR 0011): id/code/name/
     * operational_site_id plus `project_id` (spec 0094 — needed to resolve
     * the EFFECTIVE product-line categories, own or read through the linked
     * project) and the relations CampaignForSelectResource reads: the site's
     * composed label, and the campaign's own/linked-project's product lines.
     *
     * @return Builder<Campaign>
     */
    private function forSelectBaseQuery(): Builder
    {
        return Campaign::query()
            ->select(['id', 'code', 'name', 'project_id', 'operational_site_id'])
            ->with([
                'operationalSite.addresses.city',
                'operationalSite.addresses.state',
                'productLines',
                'project.productLines',
            ]);
    }

    /**
     * Append the explicitly-requested `ids[]` (edit-mode hydration) that are
     * not already on the page, deduplicated. They bypass search and the same
     * id/code/name projection applies. Total is unaffected.
     *
     * @param  Collection<int, Campaign>  $page
     * @return Collection<int, Campaign>
     */
    private function appendHydratedIds(Collection $page, ForSelectQuery $query): Collection
    {
        if (! $query->hasIds()) {
            return $page;
        }

        $presentIds = $page->pluck('id')->all();
        $missingIds = array_values(array_diff($query->ids, $presentIds));

        if ($missingIds === []) {
            return $page;
        }

        /** @var Collection<int, Campaign> $hydrated */
        $hydrated = $this->forSelectBaseQuery()
            ->whereIn('id', $missingIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }

    /**
     * BR-2: force the 3 classification fields null whenever the campaign
     * will be linked to a project once this update applies — regardless of
     * what was actually submitted (the FormRequest already rejects an
     * explicit conflicting value; this is the transition-to-linked case,
     * AC-028, plus defence in depth). BR-5's geo nulling is delegated to
     * applyGeoInheritance() (it needs the loaded $project, not just its id).
     *
     * @param  array<string, mixed>  $submitted
     * @return array<string, mixed>
     */
    private function resolveUpdateAttributes(array $submitted, ?Project $project): array
    {
        if ($project !== null) {
            $submitted['pipeline_status_id'] = null;
        }

        return $this->applyGeoInheritance($submitted, $project);
    }

    /**
     * BR-5: null out (defence in depth, on top of the FormRequest's
     * per-level `prohibited` rule) whatever geo level the linked $project
     * already fills. A level the project leaves empty is untouched here —
     * it stays whatever the campaign itself submitted/already has.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function applyGeoInheritance(array $attributes, ?Project $project): array
    {
        if ($project === null) {
            return $attributes;
        }

        foreach (self::GEO_LEVELS as $level) {
            if ($project->{$level} !== null) {
                $attributes[$level] = null;
            }
        }

        return $attributes;
    }

    /**
     * Lock the project row for the remainder of this transaction (BR-3): any
     * concurrent create/update against the SAME project's budget must wait
     * for this lock, serializing the allocated-budget check below.
     */
    private function lockProject(int $projectId): Project
    {
        return Project::query()->lockForUpdate()->findOrFail($projectId);
    }

    /**
     * BR-3: SUM(total_budget of the project's OTHER campaigns) + the
     * requested amount must not exceed the project's total_budget. No-op
     * when the project has no budget cap (A-1). `$excludingCampaignId` keeps
     * the campaign being updated from counting against its own new value
     * (AC-026). A null requested budget contributes nothing to the
     * allocation it is about to make.
     */
    private function assertBudgetAvailable(Project $project, ?float $requestedBudget, ?int $excludingCampaignId): void
    {
        if ($project->total_budget === null) {
            return;
        }

        $allocatedQuery = Campaign::query()->where('project_id', $project->id);

        if ($excludingCampaignId !== null) {
            $allocatedQuery->where('id', '!=', $excludingCampaignId);
        }

        $allocated = (float) $allocatedQuery->sum('total_budget');
        $totalBudget = (float) $project->total_budget;
        $remaining = $totalBudget - $allocated;
        $requested = $requestedBudget ?? 0.0;

        if ($requested > $remaining) {
            throw ValidationException::withMessages([
                'total_budget' => [sprintf(
                    'Budget insufficiente sul progetto %s: budget %s, già allocato %s, residuo %s, richiesto %s.',
                    $project->code,
                    $this->formatMoney($totalBudget),
                    $this->formatMoney($allocated),
                    $this->formatMoney($remaining),
                    $this->formatMoney($requested),
                )],
            ]);
        }
    }

    private function formatMoney(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
