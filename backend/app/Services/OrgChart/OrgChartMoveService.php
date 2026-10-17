<?php

declare(strict_types=1);

namespace App\Services\OrgChart;

use App\Authorization\AuthorizationRegistry;
use App\Models\BusinessFunction;
use App\Models\EmploymentProfile;
use App\Models\User;
use App\Services\Assignment\CompetenceLineSetValidator;
use App\Services\BusinessFunctionService;
use App\Services\ProductLines\ProductLineWriter;
use App\Services\TimeEntries\TimeEntrySubordinateResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The three org chart moves (spec 0273 D-4/D-6/D-8): a person under another
 * manager, a function under another parent, a person between functions. Each
 * runs in a transaction, enforces its business constraints with a 422 and
 * writes ONE `org-chart`/`moved` activity entry on the moved subject.
 *
 * Base permission (users.update / business-functions.update) is checked by the
 * controller via the Policy; the field-level permission of the data being
 * edited is checked here (403), so a locked field cannot be changed through
 * the org chart either.
 */
final class OrgChartMoveService
{
    private const string LOG_NAME = 'org-chart';

    private const string EVENT_MOVED = 'moved';

    public const string KIND_MANAGER = 'manager';

    public const string KIND_FUNCTION_PARENT = 'function_parent';

    public const string KIND_PERSON_FUNCTION = 'person_function';

    public function __construct(
        private readonly AuthorizationRegistry $authorization,
        private readonly TimeEntrySubordinateResolver $subordinates,
        private readonly BusinessFunctionService $functions,
        private readonly CompetenceLineSetValidator $competenceValidator,
        private readonly ProductLineWriter $productLineWriter,
    ) {}

    public function moveManager(User $actor, User $subject, ?int $fromId, ?int $toId): void
    {
        $this->assertFieldEditable($actor, 'users', 'employment.reports_to_ids', $subject);

        DB::transaction(function () use ($actor, $subject, $fromId, $toId): void {
            // Step 1: validate against the person's current reporting lines.
            $profile = $subject->employment ?? $this->fail('no_employment', 'from_manager_id');
            $currentIds = $profile->reportsTo()->pluck('users.id')->map(intval(...))->all();
            $this->assertManagerMove($subject, $profile, $currentIds, $fromId, $toId);

            // Step 2: replace `from` with `to`, keeping the other managers.
            $targetIds = array_values(array_unique(array_filter(
                [...array_diff($currentIds, [$fromId]), $toId],
                static fn (?int $id): bool => $id !== null,
            )));

            if ($this->sameSet($currentIds, $targetIds)) {
                return;
            }

            $profile->reportsTo()->sync($targetIds);

            // Step 3: record the move.
            $this->logMove($actor, $subject, self::KIND_MANAGER, $this->userRef($fromId), $this->userRef($toId));
        });
    }

    public function moveFunctionParent(User $actor, BusinessFunction $function, ?int $parentId): void
    {
        $this->assertFieldEditable($actor, 'business-functions', 'parent_id', $function);

        DB::transaction(function () use ($actor, $function, $parentId): void {
            // Step 1: anti-cycle guard shared with the function edit form.
            if ($parentId !== null) {
                $this->functions->assertNoCycle($function, $parentId);
            }

            if ($function->parent_id === $parentId) {
                return;
            }

            // Step 2: re-parent and record the move.
            $fromId = $function->parent_id;
            $function->update(['parent_id' => $parentId]);

            $this->logMove($actor, $function, self::KIND_FUNCTION_PARENT, $this->functionRef($fromId), $this->functionRef($parentId));
        });
    }

    /**
     * @return list<int> the person's function ids after the move
     */
    public function movePersonFunction(User $actor, User $subject, int $fromId, int $toId): array
    {
        $this->assertFieldEditable($actor, 'users', 'employment.product_lines', $subject);

        return DB::transaction(function () use ($actor, $subject, $fromId, $toId): array {
            // Step 1: the person must be a member of `from` through their own rows.
            $profile = $subject->employment ?? $this->fail('no_employment', 'from_function_id');

            if ($profile->covers_all_product_categories) {
                $this->fail('covers_all', 'from_function_id');
            }

            $rows = $profile->productLines()->get(['business_function_id', 'product_category_id'])
                ->map(fn ($line): array => [
                    'business_function_id' => (int) $line->business_function_id,
                    'product_category_id' => $line->product_category_id === null ? null : (int) $line->product_category_id,
                ]);

            if ($rows->where('business_function_id', $fromId)->isEmpty()) {
                $this->fail('no_line_on_source', 'from_function_id');
            }

            // Step 2: rows of other functions stay; `from` rows are replaced by
            // ONE whole-function row on `to`, unless the person already has rows there.
            $result = $rows->reject(fn (array $row): bool => $row['business_function_id'] === $fromId)->values();

            if ($result->where('business_function_id', $toId)->isEmpty()) {
                $result->push(['business_function_id' => $toId, 'product_category_id' => null]);
            }

            // Step 3: validate the resulting set, then write it.
            $errors = $this->competenceValidator->crossRowErrors($result->all(), 'product_lines');

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $this->productLineWriter->sync($profile, $result->all());

            $this->logMove($actor, $subject, self::KIND_PERSON_FUNCTION, $this->functionRef($fromId), $this->functionRef($toId));

            return $result->pluck('business_function_id')->unique()->sort()->values()->all();
        });
    }

    /**
     * @param  list<int>  $currentIds
     */
    private function assertManagerMove(User $subject, EmploymentProfile $profile, array $currentIds, ?int $fromId, ?int $toId): void
    {
        if ($fromId !== null && ! in_array($fromId, $currentIds, true)) {
            $this->fail('manager_not_current', 'from_manager_id');
        }

        if ($toId === null) {
            return;
        }

        if ($profile->is_manager) {
            $this->fail('manager_cannot_report', 'to_manager_id');
        }

        if ($toId === $subject->id || in_array($toId, $this->subordinates->allDescendantIds($subject->id), true)) {
            $this->fail('manager_cycle', 'to_manager_id');
        }

        if (! User::query()->excludingServiceAccounts()->whereKey($toId)->where('is_active', true)->exists()) {
            $this->fail('manager_inactive', 'to_manager_id');
        }
    }

    private function assertFieldEditable(User $actor, string $resource, string $field, Model $model): void
    {
        $permission = $this->authorization->resolve($resource)->fieldPermissions($actor, $model)[$field] ?? null;

        abort_unless($permission !== null && $permission->editable, 403, __('org_chart.field_locked'));
    }

    /**
     * @return never
     */
    private function fail(string $key, string $field): void
    {
        throw ValidationException::withMessages([$field => __("org_chart.{$key}")]);
    }

    /**
     * @param  list<int>  $a
     * @param  list<int>  $b
     */
    private function sameSet(array $a, array $b): bool
    {
        sort($a);
        sort($b);

        return $a === $b;
    }

    /**
     * @return array{id: int, name: string|null}|null
     */
    private function userRef(?int $id): ?array
    {
        return $id === null ? null : ['id' => $id, 'name' => User::query()->whereKey($id)->value('name')];
    }

    /**
     * @return array{id: int, name: string|null}|null
     */
    private function functionRef(?int $id): ?array
    {
        return $id === null ? null : ['id' => $id, 'name' => BusinessFunction::query()->whereKey($id)->value('name')];
    }

    /**
     * @param  array{id: int, name: string|null}|null  $from
     * @param  array{id: int, name: string|null}|null  $to
     */
    private function logMove(User $actor, Model $subject, string $kind, ?array $from, ?array $to): void
    {
        activity()
            ->useLog(self::LOG_NAME)
            ->performedOn($subject)
            ->causedBy($actor)
            ->event(self::EVENT_MOVED)
            ->withProperties(['kind' => $kind, 'from' => $from, 'to' => $to])
            ->log(self::EVENT_MOVED);
    }
}
