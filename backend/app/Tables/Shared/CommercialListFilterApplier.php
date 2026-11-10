<?php

declare(strict_types=1);

namespace App\Tables\Shared;

use App\Enums\CommercialAssignmentScope;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Assignment and category filters of the Opportunità, Offerte and Contratti
 * pages. The roles (Supervisore, Gestori account) are the ones their visibility
 * scopes read; how a record reaches its categories is each module's own rule.
 */
final class CommercialListFilterApplier
{
    private const string ENUM_KEY = 'commercial_assignment_scope';

    public function __construct(private readonly PresetListFilters $presets) {}

    /**
     * @param  bool  $seesBeyondOwnRecords  false hides "visible", which would change nothing for the actor
     * @return array<int, array<string, mixed>>
     */
    public function presetFilters(string $labelNamespace, ?User $actor, bool $seesBeyondOwnRecords): array
    {
        return [
            PresetListFilters::assignmentFilter($labelNamespace, self::ENUM_KEY, [CommercialAssignmentScope::Supervisor->value, CommercialAssignmentScope::Manager->value], $seesBeyondOwnRecords ? [] : [CommercialAssignmentScope::Visible->value]),
            $this->presets->presetParentCategory(PresetListFilters::parentCategoryFilter($labelNamespace, 0), $actor),
        ];
    }

    /**
     * @param  Builder<Model>  $query
     * @param  Closure(Builder<Model>, array<int, int>): void  $applyCategories
     * @param  string|null  $assignmentRelation  the relation holding the roles, when not the record itself
     * @return bool false when $name is not one of these filters
     */
    public function apply(Builder $query, string $name, mixed $value, ?User $actor, Closure $applyCategories, ?string $assignmentRelation = null): bool
    {
        if ($name === PresetListFilters::ASSIGNMENT) {
            $this->applyAssignment($query, $value, $actor, $assignmentRelation);

            return true;
        }

        if ($name === PresetListFilters::PARENT_CATEGORY || $name === PresetListFilters::PRODUCT_CATEGORY) {
            $categoryIds = $name === PresetListFilters::PARENT_CATEGORY ? $this->presets->branchIds($value) : PresetListFilters::ids($value);

            if ($categoryIds !== null && $categoryIds !== []) {
                $applyCategories($query, $categoryIds);
            }

            return true;
        }

        return false;
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function applyAssignment(Builder $query, mixed $value, ?User $actor, ?string $relation): void
    {
        $scopes = array_filter(array_map(CommercialAssignmentScope::tryFrom(...), array_filter((array) $value, is_string(...))));

        if (in_array(CommercialAssignmentScope::Visible, $scopes, true)) {
            return;
        }

        if ($scopes === [] || $actor === null) {
            $query->whereNull($query->qualifyColumn('id'));

            return;
        }

        if ($relation === null) {
            $this->constrainToRoles($query, $scopes, $actor);

            return;
        }

        $query->whereHas($relation, fn (Builder $owner) => $this->constrainToRoles($owner, $scopes, $actor));
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<int, CommercialAssignmentScope>  $scopes
     */
    private function constrainToRoles(Builder $query, array $scopes, User $actor): void
    {
        $query->where(static function (Builder $roles) use ($scopes, $actor): void {
            foreach ($scopes as $scope) {
                $scope === CommercialAssignmentScope::Supervisor
                    ? $roles->orWhere($roles->qualifyColumn('supervisor_id'), $actor->id)
                    : $roles->orWhereHas('managers', static fn (Builder $managers) => $managers->whereKey($actor->id));
            }
        });
    }
}
