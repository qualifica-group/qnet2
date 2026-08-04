<?php

namespace App\Models;

use App\Enums\FieldChangeRequestStatus;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\FieldChangeRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A proposed change to a single protected field on a single record (spec
 * 0078): an actor without the field's dedicated permission (see
 * `App\FieldChangeRequests\ProtectedFieldRegistry`) cannot write it directly,
 * but can propose one of these instead. `subject` is polymorphic (`resource`
 * + `field` name the domain/column via the same registry, F-6) — today the
 * only wired subject is `Opportunity` (request-management's `source_id`),
 * but the row shape is domain-agnostic by design.
 *
 * `status`/`pending_key`/`handled_by_id`/`handled_at`/`handling_note` are
 * deliberately NOT fillable (D-6): only the service governing approve/reject
 * ever writes them, never raw client input. `current_value`/`current_label`
 * ARE fillable because the SERVER computes and assigns them at creation time
 * (D-6) — fillable only means "assignable via create()/fill()", not
 * "client-controlled"; `FieldChangeRequestRequest` never exposes these keys.
 */
#[Fillable([
    'resource',
    'subject_type',
    'subject_id',
    'field',
    'current_value',
    'requested_value',
    'current_label',
    'requested_label',
    'reason',
    'requested_by_id',
])]
class FieldChangeRequest extends BaseModel
{
    /** @use HasFactory<FieldChangeRequestFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'current_value' => 'json',
            'requested_value' => 'json',
            'status' => FieldChangeRequestStatus::class,
            'handled_at' => 'datetime',
        ];
    }

    /**
     * The record the change is proposed against (e.g. an `Opportunity`).
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by_id');
    }
}
