<?php

declare(strict_types=1);

namespace Integrations\Formalab\Models;

use App\Models\Registry;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Commercial partner commission of one learner, keyed by tax code (spec 0427
 * D-2): a percentage of the profit per learner or a fixed amount, and the
 * supplier registry the partner is paid through. The partner itself is the
 * "Partner commerciale" of the learner's offer.
 */
#[Fillable(['tax_code', 'percentage', 'amount', 'supplier_registry_id'])]
class FormalabLearnerCommission extends Model
{
    protected $table = 'formalab_learner_commissions';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'percentage' => 'float',
            'amount' => 'float',
            'supplier_registry_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Registry, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Registry::class, 'supplier_registry_id');
    }
}
