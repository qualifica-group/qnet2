<?php

declare(strict_types=1);

namespace App\Exceptions\Tasks;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Thrown when one or more Tasks of an all-or-nothing multi-task write cannot
 * go through it — never a partial success: the outer transaction rolls back
 * entirely before this reaches the controller. Raised by
 * App\Services\Tasks\TaskBulkService (`POST /api/tasks/bulk`, spec 0156 D-6)
 * and App\Services\Tasks\TaskCascadeCompletionService (a parent's Completa
 * closing its open sub-tasks, spec 0418 D-4). The controllers catch it ahead
 * of the generic exception handler and answer with toResponse(), which puts
 * `incompatible_tasks` at the envelope's ROOT (the frozen contract's own
 * shape) — something BaseApiController::fail()'s `$data` parameter cannot
 * express (that one nests under `data`).
 */
final class TaskBulkIncompatibleException extends RuntimeException
{
    /**
     * @param  array<int, array{id: int, reason: string}>  $incompatibleTasks
     */
    public function __construct(public readonly array $incompatibleTasks)
    {
        parent::__construct('One or more tasks are not compatible with this bulk action.');
    }

    /** The translated reason one Task's failed write records in `incompatible_tasks`. */
    public static function reasonFor(Throwable $exception): string
    {
        if ($exception instanceof ValidationException) {
            /** @var string $message */
            $message = collect($exception->errors())->flatten()->first() ?? $exception->getMessage();

            return __($message);
        }

        return __($exception->getMessage());
    }

    public function toResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => __($this->getMessage()),
            'errors' => ['task_ids' => [__($this->getMessage())]],
            'incompatible_tasks' => $this->incompatibleTasks,
        ], 422);
    }
}
