<?php

namespace App\Tables\UserSkills;

use App\Models\User;
use App\Models\UserSkill;
use App\Services\Skills\UserSkillVisibility;
use App\Tables\AbstractTableDefinition;
use App\Tables\PurchaseRequests\PurchaseRequestColumnCatalog as Catalog;
use App\Tables\PurchaseRequests\PurchaseRequestDerivedQuery as Derived;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Table definition for the `user-skills` domain (spec 0286 D-7): the register,
 * scoped by construction to the people the actor may see, so rows, exports and
 * distinct values never leave that visibility. `level` is the integer 1..4:
 * the labels are the frontend's.
 */
class UserSkillsTableDefinition extends AbstractTableDefinition
{
    private const string LABELS = 'userSkills';

    /** Relation columns: id => [relation, column], filtered by a text "contains". */
    private const array TEXT_RELATIONS = [
        'user_name' => ['user', 'name'],
        'skill_name' => ['skill', 'name'],
        'category' => ['skill', 'category'],
    ];

    /** Sortable relation columns: id => [related table, foreign key, column]. */
    private const array SORT_RELATIONS = [
        'user_name' => ['users', 'user_skills.user_id', 'name'],
        'skill_name' => ['skills', 'user_skills.skill_id', 'name'],
        'category' => ['skills', 'user_skills.skill_id', 'category'],
    ];

    public function __construct(private readonly UserSkillVisibility $visibility) {}

    public function domain(): string
    {
        return 'user-skills';
    }

    /**
     * @return class-string<UserSkill>
     */
    public function modelClass(): string
    {
        return UserSkill::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe default
    // derives UserSkillPolicy::viewAny from modelClass().

    /**
     * @return Builder<UserSkill>
     */
    public function baseQuery(): Builder
    {
        /** @var User $actor */
        $actor = Auth::user();

        $query = UserSkill::query()->with(['user:id,name', 'skill:id,name,category']);
        $this->visibility->scopeToActor($query, $actor);

        return $query;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        $column = static fn (string $id, string $type, ?string $filter = null): array => Catalog::column($id, $type, sortable: true, filter: $filter, prefix: self::LABELS);

        return [
            $column('user_name', 'text', 'text'),
            $column('skill_name', 'text', 'text'),
            $column('category', 'text', 'text'),
            $column('level', 'number', 'number'),
            $column('assessed_on', 'date', 'date'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return Catalog::filtersFor($this->columns());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return [
            ['key' => 'view', 'label' => 'actions.view', 'icon' => 'eye', 'type' => 'link', 'confirm' => false, 'permission' => 'user-skills.viewAny'],
        ];
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [['columnId' => 'assessed_on', 'direction' => 'desc']];
    }

    /**
     * @return array{limit: int}
     */
    public function defaultPagination(): array
    {
        return ['limit' => 25];
    }

    /**
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var UserSkill $row */
        return [
            'id' => $row->id,
            'user_name' => $row->user->name,
            'skill_name' => $row->skill->name,
            'category' => $row->skill->category,
            'level' => $row->level->value,
            'assessed_on' => $row->assessed_on->format('Y-m-d'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        return ['view'];
    }

    /**
     * @param  Builder<UserSkill>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        if (! isset(self::TEXT_RELATIONS[$columnId])) {
            return false;
        }

        Derived::applyText($query, self::TEXT_RELATIONS[$columnId], $filter);

        return true;
    }

    /**
     * @param  Builder<UserSkill>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        if (! isset(self::SORT_RELATIONS[$columnId])) {
            return false;
        }

        [$table, $foreignKey, $column] = self::SORT_RELATIONS[$columnId];
        Derived::orderByRelated($query, $table, $foreignKey, $column, $direction);

        return true;
    }

    /**
     * @param  Builder<UserSkill>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        return isset(self::TEXT_RELATIONS[$columnId])
            ? Derived::distinctRelated($query, self::TEXT_RELATIONS[$columnId], $search, $limit)
            : null;
    }
}
