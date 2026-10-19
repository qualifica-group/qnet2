<?php

namespace App\Http\Resources;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Contact
 *
 * Explicit output allowlist for a contact channel. `value` is hidden on the
 * model (kept out of the activity log and default serialization) but is
 * deliberately re-exposed here: the channel value is the whole point of the
 * resource, and this endpoint is authorized (contacts.view) and consumed by a
 * trusted frontend. Property access bypasses $hidden, so no makeVisible() is
 * needed — this projection IS the conscious, authorized re-exposure.
 */
class ContactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'label' => $this->label,
            'value' => $this->value,
            'is_primary' => $this->is_primary,
            'is_work' => $this->is_work,
            'contactable_type' => $this->contactable_type,
            'contactable_id' => $this->contactable_id,
            'created_at' => $this->created_at,
        ];
    }
}
