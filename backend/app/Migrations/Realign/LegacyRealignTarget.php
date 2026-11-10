<?php

declare(strict_types=1);

namespace App\Migrations\Realign;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One module the one-shot realignment can align (spec 0375): how to find its
 * records on qnet-2, what counts as a human change, which fields the report
 * compares, and how to write the legacy values. A target never commits: the
 * runner decides whether the row's transaction survives.
 */
interface LegacyRealignTarget
{
    /** The source key of the legacy endpoints (`changed-records/{source}`). */
    public function source(): string;

    /** The relative path of the legacy listing, as for the import. */
    public function endpoint(): string;

    /**
     * The record's key in the `changed` list of the legacy.
     *
     * @param  array<string, mixed>  $legacy
     */
    public function legacyKey(array $legacy): string;

    /**
     * Records already on qnet-2, keyed by legacy key. A key absent here is not migrated.
     *
     * @param  list<string>  $legacyKeys
     * @return array<string, Model>
     */
    public function findMany(array $legacyKeys): array;

    /** A fresh copy of the record, so a snapshot never reads what a previous step cached. */
    public function reload(Model $record): Model;

    /** Code or title shown in the report. */
    public function label(Model $record): string;

    /**
     * Model class + id(s) whose activity entries count as a change made on qnet-2 (D-8).
     *
     * @return list<array{0: class-string<Model>, 1: int|string|Builder<Model>|null}>
     */
    public function touchSubjects(Model $record): array;

    /**
     * Every field the module aligns, by the label the report shows. Values are scalars or JSON strings.
     *
     * @return array<string, string|int|float|bool|null>
     */
    public function snapshot(Model $record): array;

    /**
     * Writes the legacy values that differ, quietly. Throwing fails only this row.
     *
     * @param  array<string, mixed>  $legacy
     */
    public function realign(Model $record, array $legacy, LegacyRealignNotes $notes): void;

    /** Whether a change to this report field moves an offer's amounts (D-15). */
    public function isAmountField(string $field): bool;
}
