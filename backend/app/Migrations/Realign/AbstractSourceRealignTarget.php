<?php

declare(strict_types=1);

namespace App\Migrations\Realign;

use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRegistry;
use App\Models\MigrationRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * A module whose migration source can already realign an imported record (spec
 * 0372): the source runs with an in-memory `update_existing` run. Nothing is
 * stored on `migration_runs`, and the runner has already found the record, so
 * the source never reaches its import path.
 */
abstract class AbstractSourceRealignTarget extends AbstractLegacyRealignTarget
{
    public function __construct(private readonly MigrationRegistry $registry) {}

    public function endpoint(): string
    {
        return $this->migrationSource()->endpoint();
    }

    public function realign(Model $record, array $legacy, LegacyRealignNotes $notes): void
    {
        $context = new MigrationImportContext((new MigrationRun)->forceFill(['update_existing' => true]), new User);

        foreach ($this->migrationSource()->realignExistingRecord($context, $legacy)->warnings as $warning) {
            $notes->warn($warning);
        }
    }

    private function migrationSource(): AbstractMigrationSource
    {
        /** @var AbstractMigrationSource $source */
        $source = $this->registry->resolve($this->source());

        return $source;
    }
}
