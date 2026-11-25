<?php

namespace App\Http\Resources;

use App\Authorization\LegacyIdVisibility;
use App\Models\CommissionConfiguration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CommissionConfiguration */
class CommissionConfigurationResource extends JsonResource
{
    /**
     * @param  array<string, array{visible: bool}>  $fieldPermissions
     */
    public function __construct($resource, private readonly array $fieldPermissions = [])
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $payload = [
            'id' => $this->id,
            'old_id' => $this->when(LegacyIdVisibility::allows($request->user()), $this->old_id),
            'name' => $this->name,
            'recipient_role' => $this->recipient_role->value,
            'application_scope' => $this->application_scope->value,
            'product_category_ids' => $this->productCategories->pluck('id')->all(),
            'product_categories' => $this->productCategories->sortBy('name')->map(self::summary(...))->values()->all(),
            'product_ids' => $this->products->pluck('id')->all(),
            'products' => $this->products->sortBy('name')->map(self::summary(...))->values()->all(),
            // Spec 0089: `recipient_type` is read-only (D-7, AC-011) and,
            // like `product_ids`/`products`, `recipient_id`/`recipient` fall
            // together under the SAME field permission (D-12).
            'recipient_type' => $this->recipient_type,
            'recipient_id' => $this->recipient_id,
            'recipient' => $this->recipient === null ? null : ['id' => $this->recipient->id, 'name' => $this->recipient->name],
            'commission_type' => $this->commission_type->value,
            'supplier_commission_direction' => $this->supplier_commission_direction?->value,
            'value' => $this->value,
            'priority' => $this->priority,
            'valid_from' => $this->valid_from?->format('Y-m-d'),
            'valid_until' => $this->valid_until?->format('Y-m-d'),
            'status' => $this->status->value,
            'internal_note' => $this->internal_note,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        foreach ($this->fieldPermissions as $field => $permission) {
            if (($permission['visible'] ?? true) === true) {
                continue;
            }

            unset($payload[$field]);

            if ($field === 'product_category_ids') {
                unset($payload['product_categories']);
            } elseif ($field === 'product_ids') {
                unset($payload['products']);
            } elseif ($field === 'recipient_id') {
                unset($payload['recipient_type'], $payload['recipient']);
            }
        }

        return $payload;
    }

    /** @return array{id: int, name: string} */
    private static function summary(Model $related): array
    {
        return ['id' => $related->id, 'name' => $related->name];
    }
}
