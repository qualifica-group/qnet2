<?php

namespace App\Migrations\Sources;

use App\DataObjects\BusinessFunctions\CreateBusinessFunctionData;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Support\ExternalApiClient;
use App\Models\BusinessFunction;
use App\Models\User;
use App\Services\BusinessFunctionService;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * `business-functions` migration source (spec 0013 AC-010): creates the
 * business function via BusinessFunctionService, then remaps the external
 * `user_ids` to qnet users via `old_id` BEFORE the create call — the users
 * are attached through CreateBusinessFunctionData/service's own pivot sync
 * (same as UsersSource remapping role names), never a second, duplicated
 * pivot write. An unresolved user reference is a non-fatal warning: the
 * function is created regardless, just without that user.
 *
 * A function the seed already provides under the same name, compared without
 * case ("Formazione" / "FORMAZIONE"), and not yet claimed by another external
 * id is ADOPTED instead (user directive 2026-10-14): it takes the `old_id` and
 * the resolved users, additively, so the categories and members the later
 * sources remap land on the one function instead of a duplicate.
 */
class BusinessFunctionsSource extends AbstractMigrationSource
{
    public function __construct(
        ExternalApiClient $client,
        private readonly BusinessFunctionService $service,
    ) {
        parent::__construct($client);
    }

    public function key(): string
    {
        return 'business-functions';
    }

    public function label(): string
    {
        return 'Business functions';
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    protected function nativeColumns(): array
    {
        return [
            ['id' => 'id', 'label' => 'ID', 'type' => 'number'],
            ['id' => 'name', 'label' => 'Name', 'type' => 'string'],
            ['id' => 'type', 'label' => 'Type', 'type' => 'string'],
        ];
    }

    public function endpoint(): string
    {
        return 'business-functions';
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
            'name' => $record['name'] ?? null,
            'type' => $record['type'] ?? null,
        ];
    }

    protected function processRow(MigrationImportContext $context, array $record): MigrationRowOutcome
    {
        $externalId = $this->externalId($record);

        if ($externalId === null) {
            throw new RuntimeException('External id is required.');
        }

        if ($this->existsByOldId(BusinessFunction::class, $externalId)) {
            return MigrationRowOutcome::skipped();
        }

        $name = trim((string) ($record['name'] ?? ''));

        if ($name === '') {
            throw new RuntimeException('name is required.');
        }

        [$userIds, $warnings] = $this->resolveUserIds((array) ($record['user_ids'] ?? []));

        $adopted = $this->unclaimedNamed($name);

        if ($adopted !== null) {
            $adopted->old_id = $externalId;
            $adopted->save();
            $adopted->users()->syncWithoutDetaching($userIds);

            return MigrationRowOutcome::created($warnings, $adopted);
        }

        $businessFunction = $this->service->create(
            $context->actor,
            new CreateBusinessFunctionData(
                name: $name,
                type: $record['type'] ?? null,
                users: $userIds === [] ? null : $userIds,
            ),
        );

        $businessFunction->old_id = $externalId;
        $businessFunction->save();

        return MigrationRowOutcome::created($warnings, $businessFunction);
    }

    private function unclaimedNamed(string $name): ?BusinessFunction
    {
        return BusinessFunction::query()
            ->whereNull('old_id')
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
            ->orderBy('id')
            ->first();
    }

    /**
     * Remap the external user references to qnet user ids via `old_id`. A
     * reference that resolves to no migrated user becomes a non-fatal
     * warning (the business function is still created, just without that
     * user attached).
     *
     * @param  array<int, int|string>  $externalUserIds
     * @return array{0: array<int, int>, 1: array<int, string>}
     */
    private function resolveUserIds(array $externalUserIds): array
    {
        $ids = [];
        $warnings = [];

        foreach ($externalUserIds as $externalUserId) {
            $userId = $this->resolveOldId(User::class, $externalUserId);

            if ($userId === null) {
                $warnings[] = "Unresolved user reference (external id {$externalUserId}).";

                continue;
            }

            $ids[] = $userId;
        }

        return [$ids, $warnings];
    }
}
