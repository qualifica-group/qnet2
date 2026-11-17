<?php

declare(strict_types=1);

namespace App\Tables\Concerns;

/**
 * The quick search of the grid matches the record's own `id` (exact match)
 * and never the legacy `old_id`: users look records up by the code and the
 * id qnet-2 shows them (user decision 2026-11-17, work orders, opportunities,
 * quotes, contracts). Overrides InjectsDefaultIdColumn's two hooks.
 */
trait SearchesByIdNotLegacyId
{
    protected function quickSearchesById(): bool
    {
        return true;
    }

    protected function quickSearchesByLegacyId(): bool
    {
        return false;
    }
}
