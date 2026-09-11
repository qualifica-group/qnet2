<?php

declare(strict_types=1);

namespace App\DataObjects\Tasks;

/**
 * Validated payload for POST /api/tasks/{task}/complete (spec 0116,
 * data_contract). Both fields optional and independent:
 * `validation_status_id` decides which of the document's two cases the
 * action lands on (CASO 2 when submitted and non-null, CASO 1 otherwise —
 * App\Services\Tasks\TaskActionService::complete() branches on the
 * `*Submitted` flag alone, never on a default value that could collide with
 * a legitimate id). `closure_feedback` applies to either case and follows
 * the nullable-column convention of UpdateTaskData: the flag, not the
 * value, decides whether the column is written.
 */
final readonly class CompleteTaskData
{
    public function __construct(
        public ?string $closureFeedback = null,
        public bool $closureFeedbackSubmitted = false,
        public ?int $validationStatusId = null,
        public bool $validationStatusIdSubmitted = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            closureFeedback: array_key_exists('closure_feedback', $data) && $data['closure_feedback'] !== null
                ? (string) $data['closure_feedback']
                : null,
            closureFeedbackSubmitted: array_key_exists('closure_feedback', $data),
            validationStatusId: array_key_exists('validation_status_id', $data) && $data['validation_status_id'] !== null
                ? (int) $data['validation_status_id']
                : null,
            validationStatusIdSubmitted: array_key_exists('validation_status_id', $data) && $data['validation_status_id'] !== null,
        );
    }
}
