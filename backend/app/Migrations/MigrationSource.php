<?php

namespace App\Migrations;

/**
 * Contract for a single external-system resource migration (spec 0013).
 *
 * A source declares everything about its migration: its registry key/label,
 * its preview column catalogue, how to page the external system read-only
 * (preview) and how to import every page in the background, creating records
 * through the EXISTING domain Services and setting `old_id` post-create
 * (never duplicated creation logic here). The generic MigrationRegistry +
 * MigrationController + RunMigrationJob operate ONLY through this contract —
 * adding a resource is one class + one config line, mirroring
 * App\Tables\TableDefinition / App\Imports\ImportDefinition.
 *
 * Authorization is NOT part of this contract: access to every migrations
 * endpoint is a single hard gate (EnsureSuperAdmin), not a per-resource
 * permission (spec 0013 decision).
 *
 * @phpstan-type MigrationColumn array{id: string, label: string, type: string}
 */
interface MigrationSource
{
    /**
     * Registry key, e.g. "roles" — the route {source} segment and the
     * `source` value persisted on the MigrationRun.
     */
    public function key(): string;

    /**
     * Human-readable label for the source picker.
     */
    public function label(): string;

    /**
     * Ordered column catalogue for the read-only preview table.
     *
     * @return array<int, array{id: string, label: string, type: string}>
     */
    public function columns(): array;

    /**
     * The sources to import BEFORE this one, each required or optional.
     * Every key is a registered source in an earlier MigrationOrder phase.
     *
     * @return list<MigrationDependency>
     */
    public function dependencies(): array;

    /**
     * Whether this installation may import the source. An unavailable source
     * stays listed but is refused by the single import and never included in
     * the mass import (spec 0285, D-5).
     */
    public function isAvailable(): bool;

    /**
     * Relative path (under config('migrations.base_url')) of this resource's
     * list endpoint on the external system — the single source of truth also
     * used internally to fetch every page (preview and import alike), and
     * surfaced read-only to the super-admin via the columns endpoint's
     * "expected template" (spec 0013).
     */
    public function endpoint(): string;

    /**
     * Fetch one normalized page from the external system (phase 1, read-only,
     * no writes). Never called with a stale/invalid page (validated upstream
     * by MigrationPreviewRequest).
     */
    public function preview(MigrationQuery $query): MigrationPage;

    /**
     * Page through the ENTIRE external source and import every row (phase 2,
     * background): create via the domain Service, set `old_id` post-create,
     * apply relational remaps via `old_id`, and accumulate the run's counters
     * and report. Idempotent: a row whose `old_id` already exists is skipped.
     */
    public function import(MigrationImportContext $context): void;
}
