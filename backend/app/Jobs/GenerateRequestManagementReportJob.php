<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ExportStatus;
use App\Enums\RequestManagementReportRowMode;
use App\Models\ExportRun;
use App\Models\User;
use App\Services\RequestManagement\Report\ReportOperatorFilter;
use App\Services\RequestManagement\Report\RequestManagementReportGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Async generation of the request-management report file (spec 0106, D-8): a
 * bespoke job, deliberately NOT GenerateExportJob (that one is tied to a
 * TableDefinition and would deform this aggregate's shape).
 *
 * TWO mandatory traps, both verified on the existing export engine
 * (ExportService::generate()) and both covered by
 * AC-003-quater/AC-003-quinquies:
 *  1. Auth::setUser($actor) BEFORE any query — RequestManagementScope is
 *     fail-closed and reads the auth guard; on a real worker, skipping this
 *     makes the report come out EMPTY instead of scoped.
 *  2. App::setLocale() from the run's FROZEN locale, never
 *     config('app.locale') — the SetLocale middleware never runs in queue.
 */
class GenerateRequestManagementReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $exportRunId) {}

    public function handle(RequestManagementReportGenerator $generator): void
    {
        set_time_limit(0);

        /** @var ExportRun $run */
        $run = ExportRun::query()->findOrFail($this->exportRunId);

        try {
            $actor = $this->freezeContext($run);
            $this->write($run, $actor, $generator);
        } catch (Throwable $exception) {
            $run->update(['status' => ExportStatus::Failed]);

            throw $exception;
        }
    }

    /**
     * Step 1 of handle(): the two D-8 traps — actor + locale, both frozen in
     * ExportRun.state at request time.
     */
    private function freezeContext(ExportRun $run): User
    {
        /** @var array{date_from: string, date_to: string, locale: string, category_keys: array<int, string>, row_mode: string, operator_keys?: array<int, string>} $state */
        $state = $run->state;

        /** @var User $actor */
        $actor = User::query()->findOrFail($run->user_id);
        Auth::setUser($actor);

        App::setLocale($state['locale']);

        return $actor;
    }

    /**
     * Step 2 of handle(): generate the file in the run's own format, persist
     * the completed run.
     */
    private function write(ExportRun $run, User $actor, RequestManagementReportGenerator $generator): void
    {
        /** @var array{date_from: string, date_to: string, locale: string, category_keys: array<int, string>, row_mode: string, operator_keys?: array<int, string>} $state */
        $state = $run->state;

        $disk = Storage::disk((string) config('exports.disk'));
        $directory = (string) config('exports.directory');
        $disk->makeDirectory($directory);

        // The format is read from the run itself, not from the frozen state:
        // ExportRun::$format is already its own column and drives the download's
        // Content-Type, so a second copy could only ever disagree with it.
        $path = $directory.'/'.Str::uuid().'.'.$run->format->extension();

        // AC-035: category_keys/row_mode are RE-READ from the frozen state,
        // never a default — a run created with two categories must produce
        // a file with exactly those two, however it later gets executed.
        // operator_keys (spec 0108) is read the same way but is OPTIONAL: a run
        // frozen before that spec has no such key, and its absence is the
        // "every operator" default, not an error (AC-014).
        $rowCount = $generator->generate(
            $actor,
            $state['date_from'],
            $state['date_to'],
            $state['category_keys'],
            RequestManagementReportRowMode::from($state['row_mode']),
            $run->format,
            $disk->path($path),
            ReportOperatorFilter::fromKeysOrAll($state['operator_keys'] ?? null),
        );

        $run->update([
            'file_path' => $path,
            'row_count' => $rowCount,
            'status' => ExportStatus::Completed,
        ]);
    }
}
