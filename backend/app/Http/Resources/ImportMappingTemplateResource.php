<?php

namespace App\Http\Resources;

use App\Models\ImportMappingTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The frozen `mapping_template` shape (spec 0035 data_contract), shared by
 * list/create responses. `created_by` never exposes the creator's email/PII,
 * mirroring TableFilterViewResource's `owner_name`.
 *
 * @mixin ImportMappingTemplate
 */
class ImportMappingTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'columns' => $this->columns,
            'column_mapping' => $this->column_mapping,
            'value_mappings' => $this->value_mappings,
            'dedup_strategy' => $this->dedup_strategy,
            'created_by' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            'created_at' => $this->created_at,
        ];
    }
}
