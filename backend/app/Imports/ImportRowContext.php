<?php

namespace App\Imports;

use App\Models\User;

/**
 * Per-row metadata handed to ImportDefinition::validateRow(), alongside the
 * row's values. Carries the row's 1-based position in the data (header
 * excluded) — enough for a definition that wants to embed the row number in
 * one of its own error messages — and the ACTOR running the import, so a
 * definition can apply actor-scoped rules (e.g. UsersImportDefinition
 * rejecting a role the importing actor is not allowed to assign — the same
 * privilege-escalation guard the real POST /api/users endpoint enforces) —
 * and, at staging, the run's configuration-step values, so a row can be
 * checked against a run-wide default (spec 0176: the leads' Fonte).
 * Dedup state is tracked separately by ImportRowProcessor (definitions never
 * see or mutate it).
 */
final readonly class ImportRowContext
{
    public function __construct(
        public int $rowNumber,
        public User $actor,
        /** @var array<string, mixed> */
        public array $globalConfig = [],
    ) {}
}
