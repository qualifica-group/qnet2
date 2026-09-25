<?php

namespace App\Http\Controllers\Table;

use App\Enums\FilterViewVisibility;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Table\TableFilterViewRequest;
use App\Http\Resources\TableFilterViewResource;
use App\Models\TableFilterView;
use App\Models\User;
use App\Services\TableFilterViewService;
use App\Tables\TableRegistry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Saved filter views (spec 0007): named, savable AG Grid filter sets per
 * domain, private or shared, extended by spec 0158 with generic E/O custom
 * filter rules and per-user favorites. Thin controller: no business logic,
 * no queries.
 *
 * List/create resolve the TableDefinition for {domain} (unknown → 404) and
 * enforce the definition's viewAny server-side (deny → 403), exactly like the
 * rest of TableController. Update/delete additionally enforce
 * TableFilterViewPolicy (owner only) and require the bound {filterView} to
 * belong to the route's {domain} — a mismatch 404s (never 403), so views
 * never leak across domains. store()/update() additionally require
 * `table-filter-views.publish` for `visibility: shared` (spec 0158, D-3).
 * favorite()/unfavorite() require the bound {filterView} to be VISIBLE to the
 * actor (own, or `shared`) as well as same-domain — either miss 404s.
 *
 * @see TableFilterViewService
 * @see docs/specs/0007-saved-filter-views.md
 */
class TableFilterViewController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly TableRegistry $registry,
        private readonly TableFilterViewService $service,
    ) {}

    /**
     * GET /api/tables/{domain}/filter-views — the actor's own views plus other
     * users' shared views for the domain.
     */
    public function index(Request $request, string $domain): JsonResponse
    {
        try {
            $definition = $this->registry->resolve($domain); // 404 if unknown

            /** @var User $actor */
            $actor = $request->user();
            $this->authorizeViewAny($definition->authorizeViewAny($actor));

            $views = $this->service->list($definition, $actor);

            return $this->ok(TableFilterViewResource::collection($views));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * POST /api/tables/{domain}/filter-views — save the current filter set as
     * a new named view, owned by the actor.
     */
    public function store(TableFilterViewRequest $request, string $domain): JsonResponse
    {
        try {
            $definition = $this->registry->resolve($domain); // 404 if unknown

            /** @var User $actor */
            $actor = $request->user();
            $this->authorizeViewAny($definition->authorizeViewAny($actor));
            $this->authorizePublish($request->visibilityInput());

            $view = $this->service->create(
                $definition,
                $actor,
                $request->nameInput(),
                $request->filtersInput(),
                $request->visibilityInput(),
                $request->advancedFiltersInput(),
                $request->rulesInput(),
            );

            return $this->created(new TableFilterViewResource($view));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT /api/tables/{domain}/filter-views/{filterView} — full update of an
     * owned view.
     */
    public function update(TableFilterViewRequest $request, string $domain, TableFilterView $filterView): JsonResponse
    {
        try {
            $definition = $this->registry->resolve($domain); // 404 if unknown
            $this->assertBelongsToDomain($filterView, $domain);

            $this->authorize('update', $filterView);
            $this->authorizePublish($request->visibilityInput());

            $view = $this->service->update(
                $definition,
                $filterView,
                $request->nameInput(),
                $request->filtersInput(),
                $request->visibilityInput(),
                $request->advancedFiltersInput(),
                $request->rulesInput(),
            );

            return $this->ok(new TableFilterViewResource($view));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['filterView' => $filterView->id]);
        }
    }

    /**
     * DELETE /api/tables/{domain}/filter-views/{filterView} — delete an owned
     * view.
     */
    public function destroy(Request $request, string $domain, TableFilterView $filterView): JsonResponse
    {
        try {
            $this->registry->resolve($domain); // 404 if unknown
            $this->assertBelongsToDomain($filterView, $domain);

            $this->authorize('delete', $filterView);

            $this->service->delete($filterView);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['filterView' => $filterView->id]);
        }
    }

    /**
     * POST /api/tables/{domain}/filter-views/{filterView}/favorite — mark a
     * visible view (own or `shared`) as favorite for the actor. Idempotent.
     */
    public function favorite(Request $request, string $domain, TableFilterView $filterView): JsonResponse
    {
        try {
            $definition = $this->registry->resolve($domain); // 404 if unknown

            /** @var User $actor */
            $actor = $request->user();
            $this->authorizeViewAny($definition->authorizeViewAny($actor));
            $this->assertVisibleInDomain($filterView, $domain, $actor);

            $view = $this->service->favorite($definition, $filterView, $actor);

            return $this->ok(new TableFilterViewResource($view));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['filterView' => $filterView->id]);
        }
    }

    /**
     * DELETE /api/tables/{domain}/filter-views/{filterView}/favorite —
     * unmark the favorite for the actor. Idempotent.
     */
    public function unfavorite(Request $request, string $domain, TableFilterView $filterView): JsonResponse
    {
        try {
            $definition = $this->registry->resolve($domain); // 404 if unknown

            /** @var User $actor */
            $actor = $request->user();
            $this->authorizeViewAny($definition->authorizeViewAny($actor));
            $this->assertVisibleInDomain($filterView, $domain, $actor);

            $view = $this->service->unfavorite($definition, $filterView, $actor);

            return $this->ok(new TableFilterViewResource($view));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['filterView' => $filterView->id]);
        }
    }

    /**
     * A bound {filterView} whose domain does not match the route {domain}
     * must never leak cross-domain: surfaced as 404 (not 403), identical to an
     * unknown id.
     *
     * @throws ModelNotFoundException
     */
    private function assertBelongsToDomain(TableFilterView $filterView, string $domain): void
    {
        if ($filterView->domain !== $domain) {
            throw (new ModelNotFoundException)->setModel(TableFilterView::class, [$filterView->id]);
        }
    }

    /**
     * Favorite/unfavorite visibility guard (spec 0158): a view of another
     * domain, or a PRIVATE view of another user, must never leak — surfaced
     * as 404 (not 403), identical to an unknown id (mirrors
     * assertBelongsToDomain()).
     *
     * @throws ModelNotFoundException
     */
    private function assertVisibleInDomain(TableFilterView $filterView, string $domain, User $actor): void
    {
        $visible = $filterView->domain === $domain
            && ($filterView->user_id === $actor->id || $filterView->visibility === FilterViewVisibility::Shared);

        if (! $visible) {
            throw (new ModelNotFoundException)->setModel(TableFilterView::class, [$filterView->id]);
        }
    }

    /**
     * Single enforcement point: deny → AuthorizationException → 403.
     *
     * @throws AuthorizationException
     */
    private function authorizeViewAny(bool $allowed): void
    {
        if (! $allowed) {
            throw new AuthorizationException;
        }
    }

    /**
     * Spec 0158, D-3: `visibility: shared` on a POST/PUT requires
     * `table-filter-views.publish` (super-admin passes via Gate::before).
     * `private` is always allowed — publishing is only ever about making a
     * view visible to OTHER users.
     *
     * @throws AuthorizationException
     */
    private function authorizePublish(FilterViewVisibility $visibility): void
    {
        if ($visibility === FilterViewVisibility::Shared) {
            $this->authorize('publish', TableFilterView::class);
        }
    }
}
