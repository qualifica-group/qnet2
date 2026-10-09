<?php

namespace App\Services;

use App\DataObjects\BusinessFunctions\CreateBusinessFunctionData;
use App\DataObjects\BusinessFunctions\UpdateBusinessFunctionData;
use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Models\BusinessFunction;
use App\Models\User;
use App\Services\BusinessFunctions\BusinessFunctionHierarchy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the `business-functions` resource (spec 0010): create /
 * update / delete, including the `type` -> is_business_unit/is_business_service
 * boolean remap, the `users`/`operationalSites` full-replace syncs and the
 * `parent_id` hierarchy (spec 0010 REV: anti-cycle guard on update, a
 * restrictive delete guard when children exist). The controller stays thin;
 * this Service is the single authority.
 *
 * `type` is mutually exclusive by construction: 'business_unit' sets
 * is_business_unit=true/is_business_service=false, 'business_service' the
 * inverse, and null (or absent on create) sets both false.
 */
class BusinessFunctionService
{
    private const string TYPE_BUSINESS_UNIT = 'business_unit';

    private const string TYPE_BUSINESS_SERVICE = 'business_service';

    /**
     * Relations eager-loaded on every returned model, so the Resource never
     * N+1s while hydrating manager/parent/users/operationalSites avatars and
     * addresses.
     *
     * @var array<int, string>
     */
    private const array HYDRATED_RELATIONS = ['manager.avatar', 'parent', 'users.avatar', 'operationalSites.addresses.city'];

    public function __construct(private readonly BusinessFunctionHierarchy $hierarchy) {}

    public function create(User $actor, CreateBusinessFunctionData $data): BusinessFunction
    {
        return DB::transaction(function () use ($data): BusinessFunction {
            /** @var BusinessFunction $businessFunction */
            $businessFunction = BusinessFunction::create(array_merge(
                ['name' => $data->name, 'manager_id' => $data->managerId, 'parent_id' => $data->parentId],
                $this->typeToBooleans($data->type),
            ));

            if ($data->hasUsers()) {
                $businessFunction->users()->sync($data->users);
            }

            if ($data->hasOperationalSites()) {
                $businessFunction->operationalSites()->sync($data->operationalSites);
            }

            return $businessFunction->fresh(self::HYDRATED_RELATIONS);
        });
    }

    public function update(User $actor, BusinessFunction $businessFunction, UpdateBusinessFunctionData $data): BusinessFunction
    {
        if ($data->hasParentId() && $data->parentId !== null) {
            $this->assertNoCycle($businessFunction, $data->parentId);
        }

        return DB::transaction(function () use ($businessFunction, $data): BusinessFunction {
            $attributes = $data->submittedAttributes();

            if ($data->hasType()) {
                $attributes = array_merge($attributes, $this->typeToBooleans($data->type));
            }

            // Unconditional save: fire the model's saved event even when no native
            // attribute changed, so the HasCustomFields write pipeline (spec 0021)
            // persists a custom-fields-only edit. A clean save runs no UPDATE query.
            $businessFunction->fill($attributes)->save();

            if ($data->hasUsers()) {
                $businessFunction->users()->sync($data->users);
            }

            if ($data->hasOperationalSites()) {
                $businessFunction->operationalSites()->sync($data->operationalSites);
            }

            return $businessFunction->fresh(self::HYDRATED_RELATIONS);
        });
    }

    /**
     * Restrictive delete: a function with child functions cannot be removed
     * (it would silently orphan them) — the service-level twin of the
     * `parent_id` FK's restrictOnDelete. Also restrictive (spec 0040, BR-3)
     * when referenced by at least one opportunity.
     */
    public function delete(BusinessFunction $businessFunction): void
    {
        if ($businessFunction->children()->exists()) {
            abort(409, 'This business function has child functions and cannot be deleted.');
        }

        if ($businessFunction->opportunities()->exists()) {
            abort(409, 'This business function has opportunities and cannot be deleted.');
        }

        // The business_function_user/business_function_operational_site pivot
        // rows cascade on delete (migration FK constraint), so no explicit
        // detach is needed here.
        $businessFunction->delete();
    }

    /**
     * Minimal, searchable, paginated business-function list for the for-select
     * standard (spec 0015, ADR 0011), mirroring UserService::forSelect: search
     * by `name`, order by name/id, ids[] hydrated without inflating total.
     *
     * $excludeDescendantsOf (spec 0010 REV) additionally excludes that
     * function AND every one of its descendants — the parent picker in edit
     * must never offer $self or a node that would create a cycle. Applied
     * only to the paginated/base query, mirroring how search does not reach
     * the explicit `ids[]` hydration below.
     */
    public function forSelect(ForSelectQuery $query, ?int $excludeDescendantsOf = null): ForSelectResult
    {
        $base = BusinessFunction::query()->select(['id', 'name', 'manager_id'])->with('manager:id,name');

        if ($excludeDescendantsOf !== null) {
            $excludedIds = array_merge([$excludeDescendantsOf], $this->hierarchy->descendantIds($excludeDescendantsOf));
            $base->whereNotIn('id', $excludedIds);
        }

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        $total = (clone $base)->count();

        /** @var Collection<int, BusinessFunction> $page */
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
     * Append the explicitly-requested `ids[]` (edit-mode hydration) that are not
     * already on the page, deduplicated. They bypass search and the same id/name
     * projection applies. Total is unaffected.
     *
     * @param  Collection<int, BusinessFunction>  $page
     * @return Collection<int, BusinessFunction>
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

        /** @var Collection<int, BusinessFunction> $hydrated */
        $hydrated = BusinessFunction::query()
            ->select(['id', 'name', 'manager_id'])
            ->with('manager:id,name')
            ->whereIn('id', $missingIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }

    /**
     * Map the client-facing `type` selector onto the two mutually-exclusive
     * boolean columns.
     *
     * @return array{is_business_unit: bool, is_business_service: bool}
     */
    private function typeToBooleans(?string $type): array
    {
        return [
            'is_business_unit' => $type === self::TYPE_BUSINESS_UNIT,
            'is_business_service' => $type === self::TYPE_BUSINESS_SERVICE,
        ];
    }

    /**
     * $parentId may not be $businessFunction itself, nor one of its own
     * descendants (i.e. $businessFunction may not be an ancestor of the
     * prospective new parent) — either would create a cycle in the tree.
     */
    private function assertNoCycle(BusinessFunction $businessFunction, int $parentId): void
    {
        if ($parentId === $businessFunction->id) {
            abort(422, 'A business function cannot be its own parent.');
        }

        $parent = BusinessFunction::find($parentId);

        if ($parent !== null && $this->hierarchy->isAncestorOf($parent, $businessFunction->id)) {
            abort(422, 'A business function cannot be moved under one of its own descendants.');
        }
    }
}
