<?php

namespace App\Models\Concerns;

use App\Enums\FieldChangeRequestStatus;
use App\Models\FieldChangeRequest;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Drop-in polymorphic field-change-requests for any model (spec 0078), the
 * same opt-in shape as `HasNotes`: `use HasFieldChangeRequests` wires the
 * `subject` morph with no schema change on the owning model.
 *
 *     class Opportunity extends BaseModel
 *     {
 *         use HasFieldChangeRequests;
 *     }
 *
 *     $opportunity->fieldChangeRequests;          // every request, any status
 *     $opportunity->pendingFieldChangeRequests;   // only the still-open ones
 *
 * `Opportunity` (request-management's `source_id`) is the first consumer;
 * the trait itself knows nothing about which fields are protected — that
 * binding lives in `config/field-change-requests.php`, read by
 * `App\FieldChangeRequests\ProtectedFieldRegistry`.
 */
trait HasFieldChangeRequests
{
    /**
     * @return MorphMany<FieldChangeRequest, $this>
     */
    public function fieldChangeRequests(): MorphMany
    {
        return $this->morphMany(FieldChangeRequest::class, 'subject');
    }

    /**
     * The subset still awaiting a manager decision — what the "pending
     * requests" badge column (F-8/AC-037) counts per record.
     *
     * @return MorphMany<FieldChangeRequest, $this>
     */
    public function pendingFieldChangeRequests(): MorphMany
    {
        return $this->fieldChangeRequests()->where('status', FieldChangeRequestStatus::Pending);
    }
}
