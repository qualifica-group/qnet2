<?php

namespace App\Http\Resources;

use App\Authorization\LegacyIdVisibility;
use App\Models\Attribute;
use App\Models\AttributeOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Attribute
 */
class AttributeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'old_id' => $this->when(LegacyIdVisibility::allows($request->user()), $this->old_id),
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'description' => $this->description,
            'help_text' => $this->help_text,
            'placeholder' => $this->placeholder,
            'icon' => $this->icon,
            'config' => $this->config,
            'relation_target' => $this->relation_target,
            'options' => $this->options->map(fn (AttributeOption $option): array => [
                'id' => $option->id,
                'value' => $option->value,
                'label' => $option->label,
                'color' => $option->color,
                'icon' => $option->icon,
                'sort_order' => $option->sort_order,
                'is_default' => $option->is_default,
            ])->all(),
            'created_at' => $this->created_at,
        ];
    }
}
