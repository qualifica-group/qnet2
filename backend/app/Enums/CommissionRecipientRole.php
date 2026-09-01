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
     * The morph aliases (`Relation::enforceMorphMap()`) this role's rule may
     * be pointed at, in order of preference — the first is the role's own
     * default (spec 0090 D-4, emends 0089 D-8's fixed one-to-one map). SINGLE
     * source of truth for the create/update FormRequest allow-list and the
     * D-9 role-change guard: duplicating this list anywhere else is exactly
     * the risk R-3 exists to prevent.
     *
     * @return array<int, string>
     */
    public function allowedRecipientTypes(): array
    {
        return match ($this) {
            self::Commercial, self::Reporter => ['referent', 'user'],
            self::Supervisor => ['user', 'referent'],
            self::Supplier => ['registry'],
        };
    }

    /** The role's default recipient type, used when `recipient_type` is omitted from the payload. */
    public function recipientType(): string
    {
        return $this->allowedRecipientTypes()[0];
    }
}
