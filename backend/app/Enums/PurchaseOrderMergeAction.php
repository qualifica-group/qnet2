<?php

namespace App\Enums;

use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * What a merge did to its source purchase order (spec 0210, D-19): deleted when
 * every line was moved, partial when some lines stayed behind.
 */
enum PurchaseOrderMergeAction: string
{
    use HasMeta;

    #[Label('Deleted')]
    case Deleted = 'deleted';

    #[Label('Partial')]
    case Partial = 'partial';
}
