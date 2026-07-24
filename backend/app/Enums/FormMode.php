<?php

namespace App\Enums;

/**
 * Which form lifecycle stage an attribute layout is configured for (spec
 * 0062, D3): create/edit/view are THREE INDEPENDENT layouts per (category,
 * context) — a category may show a compact layout while creating a record,
 * a fuller one while editing it, and a read-only grouped one on its detail
 * view. No inheritance between modes: an absent layout for one mode falls
 * back to flat rendering regardless of what the other modes have configured.
 */
enum FormMode: string
{
    case Create = 'create';
    case Edit = 'edit';
    case View = 'view';
}
