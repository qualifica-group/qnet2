<?php

declare(strict_types=1);

namespace App\Jobs\Concerns;

use App\Enums\ArchivioSmartCallResult;
use App\Enums\DocumentChecklistSyncOperation;
use App\Enums\DocumentChecklistSyncOutcome;
use App\Enums\DocumentChecklistSyncStatus;
use App\Models\ArchivioSmartSetting;
use App\Models\DocumentChecklist;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;

/**
 * Sync logic shared by the checklist and the category jobs (spec 0403, spec 0410 D-11):
 * outcome interpretation, retry/backoff, Retry-After and the sync log. It works on
 * any record with `sync_status`, `sync_error`, `synced_at` and a `syncLogs()` relation.
 * Every log row never holds the token, the headers or the body.
 *
 * The host job provides `$tries`, `attempts()` and `release()`.
 */
trait PushesToArchivioSmart
{
    /** Why the last attempt ended in ArchivioSmartCallResult::Failed. */
    protected ?string $failureMessage = null;

    /**
     * Returns the saved connection, or null after failing the record when it is missing.
     */
    protected function configuredSetting(Model $record, DocumentChecklistSyncOperation $operation): ?ArchivioSmartSetting
    {
        $setting = ArchivioSmartSetting::current();
        if ($setting === null || ! $setting->isConfigured()) {
            $this->failSync($record, $operation, null, __('archivio_smart.not_configured'));

            return null;
        }

        return $setting;
    }

    /**
     * @param  Closure(ArchivioSmartSetting): Response  $call
     */
    protected function push(Model $record, DocumentChecklistSyncOperation $operation, ArchivioSmartSetting $setting, Closure $call): ArchivioSmartCallResult
    {
        try {
            $response = $call($setting);
        } catch (ConnectionException) {
            return $this->retryOrFail($record, $operation, null, __('archivio_smart.network_error'));
        }

        return $this->interpret($record, $operation, $response);
    }

    protected function interpret(Model $record, DocumentChecklistSyncOperation $operation, Response $response): ArchivioSmartCallResult
    {
        $status = $response->status();

        return match (true) {
            in_array($status, [200, 201, 204], true) => $this->succeed($record, $operation, $status),
            $status === 422 => $this->onUnprocessable($record, $operation, $response),
            $status === 401 => $this->failSync($record, $operation, $status, __('archivio_smart.unauthorized')),
            $status === 409 => $this->failSync($record, $operation, $status, $this->conflictMessage($record)),
            $status === 429 => $this->releaseAfterRateLimit($record, $operation, $response),
            $status >= 500 => $this->retryOrFail($record, $operation, $status, __('archivio_smart.server_error', ['status' => $status])),
            default => $this->failSync($record, $operation, $status, __('archivio_smart.unexpected_status', ['status' => $status])),
        };
    }

    /**
     * A 422 is final unless the job overrides this (the category DELETE, D-10).
     */
    protected function onUnprocessable(Model $record, DocumentChecklistSyncOperation $operation, Response $response): ArchivioSmartCallResult
    {
        return $this->failSync($record, $operation, 422, $this->validationMessage($response));
    }

    protected function succeed(Model $record, DocumentChecklistSyncOperation $operation, int $status): ArchivioSmartCallResult
    {
        $attributes = [
            'sync_status' => DocumentChecklistSyncStatus::Synced,
            'sync_error' => null,
            'synced_at' => now(),
        ];
        if ($record instanceof DocumentChecklist && $operation === DocumentChecklistSyncOperation::Upsert && $record->first_synced_at === null) {
            $attributes['first_synced_at'] = now();
        }

        activity()->withoutLogs(fn () => $record->forceFill($attributes)->save());
        $this->log($record, $operation, DocumentChecklistSyncOutcome::Success, $status, null);

        return ArchivioSmartCallResult::Succeeded;
    }

    protected function failSync(Model $record, DocumentChecklistSyncOperation $operation, ?int $status, string $message): ArchivioSmartCallResult
    {
        $this->markFailed($record, $message);
        $this->log($record, $operation, DocumentChecklistSyncOutcome::Failed, $status, $message);
        $this->failureMessage = $message;

        return ArchivioSmartCallResult::Failed;
    }

    protected function retryOrFail(Model $record, DocumentChecklistSyncOperation $operation, ?int $status, string $message): ArchivioSmartCallResult
    {
        if ($this->attempts() >= $this->tries) {
            return $this->failSync($record, $operation, $status, $message);
        }

        $this->log($record, $operation, DocumentChecklistSyncOutcome::Retrying, $status, $message);
        $this->release($this->backoffSeconds());

        return ArchivioSmartCallResult::Released;
    }

    protected function releaseAfterRateLimit(Model $record, DocumentChecklistSyncOperation $operation, Response $response): ArchivioSmartCallResult
    {
        $retryAfter = $response->header('Retry-After');
        $delay = ctype_digit($retryAfter) ? (int) $retryAfter : (int) config('archivio-smart.retry_after_fallback');

        $this->log($record, $operation, DocumentChecklistSyncOutcome::Retrying, 429, null);
        $this->release($delay);

        return ArchivioSmartCallResult::Released;
    }

    protected function markFailed(Model $record, string $message): void
    {
        activity()->withoutLogs(fn () => $record->forceFill([
            'sync_status' => DocumentChecklistSyncStatus::Failed,
            'sync_error' => $this->truncate($message),
        ])->save());
    }

    protected function log(
        Model $record,
        DocumentChecklistSyncOperation $operation,
        DocumentChecklistSyncOutcome $outcome,
        ?int $status,
        ?string $message,
    ): void {
        $record->syncLogs()->create([
            'operation' => $operation,
            'outcome' => $outcome,
            'http_status' => $status,
            'attempt' => $this->attempts(),
            'message' => $message === null ? null : $this->truncate($message),
        ]);
    }

    protected function truncate(string $message): string
    {
        return Str::limit($message, (int) config('archivio-smart.error_message_max_length'), '');
    }

    private function backoffSeconds(): int
    {
        /** @var array<int, int> $backoff */
        $backoff = config('archivio-smart.backoff');

        return (int) ($backoff[$this->attempts() - 1] ?? end($backoff));
    }

    private function conflictMessage(Model $record): string
    {
        return $record instanceof DocumentChecklist
            ? __('archivio_smart.conflict')
            : __('archivio_smart.category_conflict');
    }

    protected function validationMessage(Response $response): string
    {
        $errors = $response->json('errors');
        $messages = is_array($errors) ? collect($errors)->flatten()->filter(fn (mixed $message): bool => is_string($message))->implode(' ') : '';

        return $messages !== '' ? $messages : __('archivio_smart.validation_failed');
    }
}
