<?php

namespace App\Http\Controllers\Import\Concerns;

use App\Enums\ImportDedupMode;
use App\Enums\ImportRowStatus;
use App\Enums\ImportStatus;
use App\Imports\ImportDefinition;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Storage;

/**
 * Cross-cutting guard clauses shared by every ImportController action
 * (ownership/domain match, authorization, run/row status), extracted to
 * stay under the 300/500-line limits (engineering.md §6) — pure assertions,
 * no orchestration of their own.
 */
trait GuardsImportRequests
{
    /**
     * A definition rides the unified wizard flow when it declares ANY
     * wizard-only capability: global configuration fields, `__extra__`
     * mapping support, or a dedup strategy beyond the legacy create-only
     * default. The 5 legacy definitions declare none of these (their
     * AbstractImportDefinition defaults), so this is false for them.
     */
    private function isWizardDefinition(ImportDefinition $definition): bool
    {
        if ($definition->globalConfig() !== []) {
            return true;
        }

        if ($definition->supportsExtraFields()) {
            return true;
        }

        return $definition->dedupModes() !== [ImportDedupMode::CreateOnly];
    }

    /**
     * @throws HttpResponseException via abort() when not `reviewing`.
     */
    private function assertReviewing(ImportRun $importRun): void
    {
        if ($importRun->status !== ImportStatus::Reviewing) {
            abort(422, 'The import is not in review.');
        }
    }

    /**
     * @throws HttpResponseException via abort() when the row is not `duplicate`.
     */
    private function assertRowIsDuplicate(ImportRunRow $row): void
    {
        if ($row->status !== ImportRowStatus::Duplicate) {
            abort(422, 'This row is not a duplicate.');
        }
    }

    /**
     * `rows`/`summary` serve the read-only DETAIL view in addition to the
     * wizard's own review step (spec 0034): a `completed`/`failed` run may be
     * inspected the same way a `reviewing` one is, just without the edit
     * ability (`updateRow` keeps the strict assertReviewing() above).
     *
     * @throws HttpResponseException via abort() when outside these statuses.
     */
    private function assertReadableStatus(ImportRun $importRun): void
    {
        $readable = [ImportStatus::Reviewing, ImportStatus::Completed, ImportStatus::Failed];

        if (! in_array($importRun->status, $readable, true)) {
            abort(422, 'The import is not in a state that supports viewing rows/summary.');
        }
    }

    /**
     * A {row} not belonging to the bound {importRun} 404s (never 403),
     * mirroring assertRunMatchesDomain().
     *
     * @throws ModelNotFoundException
     */
    private function assertRowBelongsToRun(ImportRunRow $row, ImportRun $importRun): void
    {
        if ($row->import_run_id !== $importRun->id) {
            throw (new ModelNotFoundException)->setModel(ImportRunRow::class, [$row->id]);
        }
    }

    /**
     * Single enforcement point: deny → AuthorizationException → 403.
     *
     * @throws AuthorizationException
     */
    private function authorizeImport(ImportDefinition $definition, User $actor): void
    {
        if (! $definition->authorizeImport($actor)) {
            throw new AuthorizationException;
        }
    }

    /**
     * A bound {importRun} whose resource does not match the route {domain}
     * must never leak cross-domain: surfaced as 404 (not 403), identical to an
     * unknown id. Runs are NOT owner-scoped: every `leads.import` holder reads
     * and manages every run (user decision 2026-09-16).
     *
     * @throws ModelNotFoundException
     */
    private function assertRunMatchesDomain(ImportRun $importRun, string $domain): void
    {
        if ($importRun->resource !== $domain) {
            throw (new ModelNotFoundException)->setModel(ImportRun::class, [$importRun->id]);
        }
    }

    /**
     * @throws ModelNotFoundException when no errors report was ever written.
     */
    private function assertHasErrorReport(ImportRun $importRun): void
    {
        if ($importRun->error_report_path === null || ! Storage::disk('local')->exists($importRun->error_report_path)) {
            throw (new ModelNotFoundException)->setModel(ImportRun::class, [$importRun->id]);
        }
    }
}
