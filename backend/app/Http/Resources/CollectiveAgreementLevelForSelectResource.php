<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\CollectiveAgreement;
use Illuminate\Http\Request;

/**
 * For-select projection of a collective agreement level (spec 0272):
 * label = "AGREEMENT NAME – CODE". Expects `agreement` loaded.
 *
 * @property int $id
 * @property string $code
 * @property CollectiveAgreement $agreement
 */
class CollectiveAgreementLevelForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return ['id' => $this->id, 'label' => "{$this->agreement->name} – {$this->code}"];
    }
}
