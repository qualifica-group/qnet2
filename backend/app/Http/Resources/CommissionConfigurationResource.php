<?php

namespace App\Http\Resources;

use App\Models\CommissionConfiguration;
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
            'name' => $this->name,
            'recipient_role' => $this->recipient_role->value,
            'application_scope' => $this->application_scope->value,
            'product_category_id' => $this->product_category_id,
            'product_category' => $this->productCategory === null ? null : [
                'id' => $this->productCategory->id,
                'name' => $this->productCategory->name,
            ],
            'product_id' => $this->product_id,
            'product' => $this->product === null ? null : ['id' => $this->product->id, 'name' => $this->product->name],
            'commission_type' => $this->commission_type->value,
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

            if ($field === 'product_category_id') {
                unset($payload['product_category']);
            } elseif ($field === 'product_id') {
                unset($payload['product']);
            }
        }

        return $payload;
    }
}
