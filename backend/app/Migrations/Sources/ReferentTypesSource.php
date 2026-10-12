<?php

namespace App\Migrations\Sources;

use App\DataObjects\ReferentTypes\CreateReferentTypeData;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Support\ExternalApiClient;
use App\Models\ReferentType;
use App\Services\ReferentTypeService;
use RuntimeException;

/**
 * `referent-types` migration source (spec 0013 / 0016): a plain lookup entity
 * (id, name) created through ReferentTypeService, mirroring RolesSource's
 * simpler slice. It is a phase-1 anchor: ReferentsSource later remaps each
 * referent's `referent_type_id` to the qnet type via `old_id`. Re-import is
 * idempotent (skip by old_id); the name is NOT unique, so no adoption logic.
 */
class ReferentTypesSource extends AbstractMigrationSource
{
    public function __construct(
        ExternalApiClient $client,
        private readonly ReferentTypeService $service,
    ) {
        parent::__construct($client);
    }

    public function key(): string
    {
        return 'referent-types';
    }

    public function label(): string
    {
        return 'Referent types';
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    protected function nativeColumns(): array
    {
        return [
            ['id' => 'id', 'label' => 'ID', 'type' => 'number'],
            ['id' => 'name', 'label' => 'Name', 'type' => 'string'],
        ];
    }

    public function endpoint(): string
    {
        return 'referent-types';
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
        ];
    }

    protected function processRow(MigrationImportContext $context, array $record): MigrationRowOutcome
    {
        $externalId = $this->externalId($record);

        if ($externalId === null) {
            throw new RuntimeException('External id is required.');
        }

        if ($this->existsByOldId(ReferentType::class, $externalId)) {
            return MigrationRowOutcome::skipped();
        }

        $name = trim((string) ($record['name'] ?? ''));

        if ($name === '') {
            throw new RuntimeException('name is required.');
        }

        $referentType = $this->service->create(new CreateReferentTypeData(name: $name));

        $referentType->old_id = $externalId;
        $referentType->save();

        return MigrationRowOutcome::created(model: $referentType);
    }
}
