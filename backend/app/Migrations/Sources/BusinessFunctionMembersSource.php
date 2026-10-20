<?php

namespace App\Migrations\Sources;

use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationDependency;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Models\BusinessFunction;
use App\Models\User;
use RuntimeException;

/**
 * `business-function-members` migration source (spec 0013, extended): the
 * association pass that links people to an already-migrated business function
 * from the FUNCTION side, so it can express BOTH roles the pivot-only junction
 * could not:
 *  - the operators (0..n) -> the `business_function_user` pivot;
 *  - the single responsible/manager -> `business_functions.manager_id`.
 *
 * It re-reads the SAME external `business-functions` endpoint BusinessFunctionsSource
 * consumes (`user_ids` for operators, `manager_id` for the responsible — all
 * EXTERNAL ids), and runs in a later phase once users exist: BusinessFunctionsSource
 * (phase 1) creates the function before any user is migrated, so its own
 * `user_ids`/manager links cannot resolve yet; this source back-fills them once
 * every user has an `old_id` (MigrationOrder phase 3).
 *
 * The links are written directly on the relations (operators via
 * `syncWithoutDetaching` — additive, never dropping members already attached;
 * the responsible via `manager_id`), which is the pivot/relational remap
 * concern spec 0013 assigns to the source. Every reference is remapped via
 * `old_id`; an unresolved one is a non-fatal warning. Re-import is idempotent:
 * a row that changes nothing is skipped, never duplicated.
 */
class BusinessFunctionMembersSource extends AbstractMigrationSource
{
    public function key(): string
    {
        return 'business-function-members';
    }

    public function label(): string
    {
        // Named for what the pass actually does (reconcile the manager +
        // operators onto an already-migrated function), so it is not mistaken
        // for the "Business functions" source in the selector.
        return 'Business functions — reconcile manager & operators';
    }

    /**
     * {@inheritDoc}
     */
    public function dependencies(): array
    {
        return [
            MigrationDependency::required('business-functions'),
            MigrationDependency::required('users'),
        ];
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    protected function nativeColumns(): array
    {
        return [
            ['id' => 'id', 'label' => 'ID', 'type' => 'number'],
            ['id' => 'manager_id', 'label' => 'Responsible (external id)', 'type' => 'number'],
        ];
    }

    /**
     * `user_ids` (the operators) is an array, not a scalar preview column, so
     * it is injected here to keep the copyable "expected response" faithful to
     * the real external contract — mirrors UsersSource::sampleResponse().
     *
     * @return array{items: array<int, array<string, mixed>>, pagination: array{total: int, offset: int, limit: int, total_pages: int}}
     */
    public function sampleResponse(): array
    {
        $sample = parent::sampleResponse();
        $sample['items'][0]['user_ids'] = [1, 2];

        return $sample;
    }

    public function endpoint(): string
    {
        return 'business-functions';
    }

    /**
     * Not in users link-only mode (spec 0285, D-5): that installation provisions
     * the function managers and operators itself, and the legacy links would
     * overwrite the manager and add operators on top of them.
     */
    public function isAvailable(): bool
    {
        return ! config('migrations.users_link_only', false);
    }

    protected function externalId(array $record): int|string|null
    {
        return $record['id'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, string|int|bool|null>
     */
    protected function mapNativeRow(array $record): array
    {
        return [
            'id' => $record['id'] ?? null,
            'manager_id' => $record['manager_id'] ?? null,
        ];
    }

    protected function processRow(MigrationImportContext $context, array $record): MigrationRowOutcome
    {
        $externalId = $this->externalId($record);

        if ($externalId === null) {
            throw new RuntimeException('External id is required.');
        }

        $functionId = $this->resolveOldId(BusinessFunction::class, $externalId);

        if ($functionId === null) {
            return MigrationRowOutcome::skipped(["Unresolved business function reference (external id {$externalId})."]);
        }

        /** @var BusinessFunction $businessFunction */
        $businessFunction = BusinessFunction::query()->findOrFail($functionId);

        $warnings = [];

        // Step 1: attach the operators additively (never detaching existing members).
        $operatorsChanged = $this->attachOperators($businessFunction, (array) ($record['user_ids'] ?? []), $warnings);

        // Step 2: set the single responsible/manager, if the external ref resolves.
        $managerChanged = $this->assignManager($businessFunction, $record['manager_id'] ?? null, $warnings);

        return ($operatorsChanged || $managerChanged)
            ? MigrationRowOutcome::created($warnings, $businessFunction)
            : MigrationRowOutcome::skipped($warnings);
    }

    /**
     * Remap each external operator id via `old_id` and attach the ones not yet
     * linked; an unresolved reference is a non-fatal warning. Returns whether
     * at least one new pivot row was created.
     *
     * @param  array<int, int|string>  $externalUserIds
     * @param  array<int, string>  $warnings
     */
    private function attachOperators(BusinessFunction $businessFunction, array $externalUserIds, array &$warnings): bool
    {
        $ids = [];

        foreach ($externalUserIds as $externalUserId) {
            $userId = $this->resolveOldId(User::class, $externalUserId);

            if ($userId === null) {
                $warnings[] = "Unresolved operator reference (external id {$externalUserId}).";

                continue;
            }

            $ids[] = $userId;
        }

        if ($ids === []) {
            return false;
        }

        $result = $businessFunction->users()->syncWithoutDetaching($ids);

        return $result['attached'] !== [];
    }

    /**
     * Resolve the external responsible reference via `old_id` and set it as the
     * function's manager when it changed; an unresolved reference is a non-fatal
     * warning that leaves the current manager untouched. Returns whether the
     * manager was updated.
     *
     * @param  array<int, string>  $warnings
     */
    private function assignManager(BusinessFunction $businessFunction, mixed $externalManagerId, array &$warnings): bool
    {
        if ($externalManagerId === null || $externalManagerId === '') {
            return false;
        }

        $managerId = $this->resolveOldId(User::class, $externalManagerId);

        if ($managerId === null) {
            $warnings[] = "Unresolved responsible reference (external id {$externalManagerId}).";

            return false;
        }

        if ($businessFunction->manager_id === $managerId) {
            return false;
        }

        $businessFunction->manager_id = $managerId;
        $businessFunction->save();

        return true;
    }
}
