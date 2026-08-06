<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\FieldChangeRequestStatus;
use App\FieldChangeRequests\ProtectedFieldRegistry;
use App\Models\FieldChangeRequest;
use App\Models\User;
use App\Services\FieldChangeRequests\FieldChangeRequestValueResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The single shape every field-change-requests endpoint returns (spec 0078,
 * `<shape name="FieldChangeRequestResource">`). `resource_label`/
 * `field_label`/`subject_path` are resolved via ProtectedFieldRegistry
 * (config-only, no query); `subject_label` goes through
 * FieldChangeRequestValueResolver — both container-resolved (app()), the
 * same convention QuoteWorkflowResource already uses: a JsonResource
 * is instantiated directly (`new FieldChangeRequestResource(...)`), so
 * constructor injection is not available here.
 *
 * Every attribute below is read off a local `$model` variable, NEVER
 * `$this->xxx`: `JsonResource` itself declares a public `$resource`
 * property (the wrapped model), which shadows `FieldChangeRequest`'s own
 * `resource` COLUMN — `$this->resource` resolves to the model instance, not
 * its `resource` attribute, for this one column name only. Reading
 * everything through `$model` sidesteps the trap uniformly.
 */
class FieldChangeRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var FieldChangeRequest $model */
        $model = $this->resource;
        /** @var User $actor */
        $actor = $request->user();

        $protectedField = app(ProtectedFieldRegistry::class)->find($model->resource, $model->field);
        $subjectLabel = app(FieldChangeRequestValueResolver::class)
            ->subjectLabelOrFallback($model->resource, (int) $model->subject_id, $actor);

        return [
            'id' => $model->id,
            'resource' => $model->resource,
            'resource_label' => $protectedField?->resourceLabel,
            'subject_id' => $model->subject_id,
            'subject_label' => $subjectLabel,
            'subject_path' => $protectedField !== null ? "{$protectedField->recordPath}/{$model->subject_id}" : null,
            'field' => $model->field,
            'field_label' => $protectedField?->fieldLabel,
            'current_value' => $model->current_value,
            'current_label' => $model->current_label,
            'requested_value' => $model->requested_value,
            'requested_label' => $model->requested_label,
            'reason' => $model->reason,
            'status' => $model->status->value,
            'requested_by' => $this->personSummary($model->requestedBy),
            'requested_at' => $model->created_at,
            'handled_by' => $this->personSummary($model->handledBy),
            'handled_at' => $model->handled_at,
            'handling_note' => $model->handling_note,
            'can' => [
                'approve' => $this->canManage($model, $actor),
                'reject' => $this->canManage($model, $actor),
            ],
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function personSummary(?User $person): ?array
    {
        return $person === null ? null : ['id' => $person->id, 'name' => $person->name];
    }

    /**
     * AC-040: true only for an actor holding `field-change-requests.manage`
     * on a request STILL `pending` — false in every other case, including
     * for the requester themself (AC-032).
     */
    private function canManage(FieldChangeRequest $model, User $actor): bool
    {
        return $model->status === FieldChangeRequestStatus::Pending
            && $actor->can('field-change-requests.manage');
    }
}
