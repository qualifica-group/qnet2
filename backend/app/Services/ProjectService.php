<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\Projects\CreateProjectData;
use App\DataObjects\Projects\ProjectIndexQuery;
use App\DataObjects\Projects\ProjectIndexResult;
use App\DataObjects\Projects\UpdateProjectData;
use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Models\Lead;
use App\Models\PipelineStatus;
use App\Models\Project;
use App\Services\Concerns\GeneratesSequentialCode;
use App\Services\Concerns\RealignsCampaignGeo;
use App\Services\ProductLines\ProductLineWriter;
use App\Services\Statuses\SystemStatusGuard;
use App\Services\Table\TableQueryBuilder;
use App\Tables\TableRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the `projects` resource (spec 0023): create/update
 * (with the server-generated PRJ-0001 code, BR-1), a restrictive delete
 * (BR-5), and the minimal/paginated for-select projection carrying the
 * campaign-form default `meta` (ADR 0011).
 */
class ProjectService
{
    use GeneratesSequentialCode;
    use RealignsCampaignGeo;

    private const string CODE_PREFIX = 'PRJ';

    private const string CODE_TABLE = 'projects';

    private const string CODE_COLUMN = 'code';

    public function __construct(
        private readonly TableRegistry $tableRegistry,
        private readonly TableQueryBuilder $queryBuilder,
        private readonly SystemStatusGuard $systemStatusGuard,
        private readonly ProductLineWriter $productLineWriter,
    ) {}

    /**
     * Relations eager-loaded for the detail/write-result read tree
     * (ProjectResource), so a single query never N+1s across the
     * classification FKs plus the 4 geo levels (spec 0027). Spec 0094,
     * D-1/D-2: `businessFunction`/`productCategory` are REPLACED by
     * `productLines.businessFunction`/`productLines.productCategory`.
     *
     * @var array<int, string>
     */
    private const array DETAIL_RELATIONS = [
        'pipelineStatus',
        'productLines.businessFunction',
        'productLines.productCategory',
        'country',
        'state',
        'province',
        'city',
        'partner',
        'operationalSite.addresses.city',
    ];

    /**
     * Eager-load the detail read tree plus the BR-7 budget aggregates
     * (allocated_budget_sum / campaigns_count), so a plain
     * GET /projects/{project} returns the SAME shape create()/update() do.
     */
    public function loadDetail(Project $project): Project
    {
        return $project->load(self::DETAIL_RELATIONS)
            ->loadSum('campaigns as allocated_budget_sum', 'total_budget')
            ->loadCount('campaigns');
    }

    /**
     * Create a new project. A manual `code` (spec 0025, BR-1) is persisted as
     * submitted; otherwise one is generated inside the transaction with a
     * pessimistic lock, so two concurrent creates never collide.
     */
    public function create(CreateProjectData $data): Project
    {
        $project = DB::transaction(function () use ($data): Project {
            $attributes = $data->attributes();

            // spec 0039, D-3: an omitted/null FK falls back to the mandatory
            // system_key='new' status (resolved by system_key, never by name).
            $attributes['pipeline_status_id'] = $data->pipelineStatusId
                ?? $this->systemStatusGuard->resolveNewStatusId(PipelineStatus::class);

            // `code` is deliberately absent from Project's #[Fillable] (BR-1),
            // so a mass-assigned Project::create() would silently drop it,
            // leaving the NOT NULL `code` column unset — assign it directly
            // (bypasses mass-assignment guarding) AFTER the fillable attributes,
            // mirroring CampaignService::create().
            $project = new Project($attributes);
            $project->code = $data->code ?? $this->nextSequentialCode(self::CODE_TABLE, self::CODE_COLUMN, self::CODE_PREFIX);
            $project->save();

            // Spec 0094, D-1/D-2: `product_lines` is a to-many collection, not
            // a mass-assignable column — StoreProjectRequest always requires
            // at least one row.
            $this->productLineWriter->sync($project, $data->productLines);

            return $project;
        });

        return $this->loadDetail($project);
    }

    /**
     * The next sequential code (PRJ-0001...) as a non-binding suggestion for
     * the create form's auto-fill (spec 0025). Lock-free: the binding value is
     * still resolved atomically in create().
     */
    public function previewNextCode(): string
    {
        return $this->peekNextSequentialCode(self::CODE_TABLE, self::CODE_COLUMN, self::CODE_PREFIX);
    }

    /**
     * Update an existing project. Only keys present in $data are touched, so
     * partial (PATCH) updates leave untouched fields as-is. Lowering
     * `total_budget` below the campaigns' already-allocated sum is allowed
     * (D-4/BR-7): this update is never blocked by budget.
     *
     * BR-5 addendum (spec 0027, "the project wins"): a project update that
     * actually claims or changes a geo level realigns every linked campaign
     * in the SAME transaction (realignLinkedCampaignsGeo()) — otherwise two
     * independently-valid requests (this update, plus an earlier campaign
     * refinement) could leave the campaign row pointing at a geo tuple that
     * is no longer coherent with the project it is locked to.
     */
    public function update(Project $project, UpdateProjectData $data): Project
    {
        DB::transaction(function () use ($project, $data): void {
            // Unconditional save: fire the model's saved event even when no native
            // attribute changed, so the HasCustomFields write pipeline (spec 0021)
            // persists a custom-fields-only edit. A clean save runs no UPDATE query.
            $project->fill($data->submittedAttributes())->save();

            if ($project->wasChanged(self::GEO_LEVELS)) {
                $this->realignLinkedCampaignsGeo($project);
            }

            // Spec 0094, D-1/D-2: `product_lines` may be omitted (partial
            // PATCH, rows untouched) but never cleared to `[]`
            // (UpdateProjectRequest's own `min:1`).
            if ($data->hasProductLines()) {
                $this->productLineWriter->sync($project, $data->productLines);
            }
        });

        return $this->loadDetail($project);
    }

    /**
     * Restrictive delete (BR-5/A-2): a project with at least one campaign
     * cannot be removed, mirroring ProductCategoryService::delete.
     */
    public function delete(Project $project): void
    {
        if ($project->campaigns()->exists()) {
            abort(409, 'This project has campaigns and cannot be deleted.');
        }

        $project->delete();
    }

    /**
     * Minimal, searchable, paginated project list for the for-select
     * standard (ADR 0011, spec 0023), carrying the campaign-form default
     * `meta` (partner/pipeline_status/state/product_lines + the BR-7 budget
     * figures) so the Campaign form can precompile its defaults with no
     * extra request.
     */
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = $this->forSelectBaseQuery();

        if ($query->hasSearch()) {
            $base->where(function (Builder $relatedQuery) use ($query): void {
                $relatedQuery->where('code', 'like', '%'.$query->search.'%')
                    ->orWhere('name', 'like', '%'.$query->search.'%');
            });
        }

        $total = (clone $base)->count();

        /** @var Collection<int, Project> $page */
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
     * The card-grid list (spec 0026, D-3), newest-first, carrying the
     * campaigns_count/leads_count aggregate counts — a single query, no N+1.
     *
     * `advancedFilters` (spec 0032, AC-018) is applied via the SAME
     * TableQueryBuilder::applyAdvancedFilters() the AG Grid `POST /rows` path
     * uses against ProjectsTableDefinition's own catalogue — one
     * implementation of "what does this advanced filter mean", never
     * duplicated per-type logic here.
     */
    public function index(ProjectIndexQuery $query): ProjectIndexResult
    {
        $base = $this->indexBaseQuery();

        if ($query->hasSearch()) {
            $base->where(function (Builder $relatedQuery) use ($query): void {
                $relatedQuery->where('code', 'like', '%'.$query->search.'%')
                    ->orWhere('name', 'like', '%'.$query->search.'%');
            });
        }

        if ($query->pipelineStatusId !== null) {
            $base->where('pipeline_status_id', $query->pipelineStatusId);
        }

        if ($query->advancedFilters !== []) {
            $this->queryBuilder->applyAdvancedFilters($this->tableRegistry->resolve('projects'), $base, $query->advancedFilters);
        }

        $total = (clone $base)->count();

        /** @var Collection<int, Project> $items */
        $items = $base->orderByDesc('created_at')
            ->orderByDesc('id')
            ->offset($query->offset)
            ->limit($query->limit)
            ->get();

        return new ProjectIndexResult(items: $items, total: $total, offset: $query->offset, limit: $query->limit);
    }

    /**
     * @return Builder<Project>
     */
    private function indexBaseQuery(): Builder
    {
        return Project::query()
            ->with(['pipelineStatus', 'country', 'state', 'province', 'city'])
            ->withCount(['campaigns', 'leads'])
            ->withSum('campaigns as allocated_budget_sum', 'total_budget');
    }

    /**
     * Global KPI tiles (spec 0026): counts of projects/campaigns/leads
     * reachable through a project. Cheap aggregate query, no N+1.
     *
     * @return array{projects_count: int, campaigns_count: int, leads_count: int}
     */
    public function summary(): array
    {
        $projectsCount = Project::query()->count();
        $campaignsCount = (int) DB::table('campaigns')->whereNotNull('project_id')->count();
        $leadsCount = (int) Lead::query()->whereHas('campaign', fn (Builder $campaignQuery) => $campaignQuery->whereNotNull('project_id'))->count();

        return [
            'projects_count' => $projectsCount,
            'campaigns_count' => $campaignsCount,
            'leads_count' => $leadsCount,
        ];
    }

    /**
     * @return Builder<Project>
     */
    private function forSelectBaseQuery(): Builder
    {
        return Project::query()
            ->select(['id', 'code', 'name', 'pipeline_status_id', 'country_id', 'state_id', 'province_id', 'city_id', 'partner_id', 'operational_site_id', 'total_budget'])
            ->with(self::DETAIL_RELATIONS)
            ->withSum('campaigns as allocated_budget_sum', 'total_budget');
    }

    /**
     * Append the explicitly-requested `ids[]` (edit-mode hydration) that are
     * not already on the page, deduplicated. They bypass search and the same
     * projection applies. Total is unaffected.
     *
     * @param  Collection<int, Project>  $page
     * @return Collection<int, Project>
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

        /** @var Collection<int, Project> $hydrated */
        $hydrated = $this->forSelectBaseQuery()
            ->whereIn('id', $missingIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }
}
