<?php

namespace Tests\Stubs;

use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;

/**
 * Migration source double that supports "update existing" (spec 0372): each
 * record's `action` decides the row outcome, and `existing` rows become
 * `updated` only when the run asked for it, as a real source does.
 */
class StubUpdatableMigrationSource extends AbstractMigrationSource
{
    public function key(): string
    {
        return 'stub-updatable';
    }

    public function label(): string
    {
        return 'Stub updatable';
    }

    public function endpoint(): string
    {
        return 'stub-updatable';
    }

    public function supportsUpdateExisting(): bool
    {
        return true;
    }

    protected function nativeColumns(): array
    {
        return [['id' => 'id', 'label' => 'ID', 'type' => 'number']];
    }

    protected function mapNativeRow(array $record): array
    {
        return ['id' => $record['id']];
    }

    protected function externalId(array $record): int|string|null
    {
        return $record['id'] ?? null;
    }

    protected function processRow(MigrationImportContext $context, array $record): MigrationRowOutcome
    {
        return match ($record['action']) {
            'create' => MigrationRowOutcome::created(),
            'existing' => $this->updatesExisting($context)
                ? MigrationRowOutcome::updated(['Realigned.'])
                : MigrationRowOutcome::skipped(),
            default => MigrationRowOutcome::skipped(['Left as is.']),
        };
    }
}
