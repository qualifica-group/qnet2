<?php

namespace App\Enums;

use App\Enums\Attributes\Color;
use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

enum CommissionRecipientRole: string
{
    use HasMeta;

    #[Label('commission_configurations.roles.commercial')]
    #[Color('blue')]
    case Commercial = 'COMMERCIAL';

    #[Label('commission_configurations.roles.reporter')]
    #[Color('violet')]
    case Reporter = 'REPORTER';

    #[Label('commission_configurations.roles.supervisor')]
    #[Color('amber')]
    case Supervisor = 'SUPERVISOR';

    #[Label('commission_configurations.roles.supplier')]
    #[Color('emerald')]
    case Supplier = 'SUPPLIER';

    /**
     * The morph alias (`Relation::enforceMorphMap()`, spec 0089 D-8) of the
     * recipient entity this role's commission may be awarded to. Single
     * source of truth: previously duplicated as an explicit map in
     * `QuoteLineCommissionWriter` and an implicit one in
     * `CommissionRecipientResolver`.
     */
    public function recipientType(): string
    {
        return match ($this) {
            self::Commercial, self::Reporter => 'referent',
            self::Supervisor => 'user',
            self::Supplier => 'registry',
        };
    }
}
