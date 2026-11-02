<?php

declare(strict_types=1);

namespace App\Tables;

/**
 * A table definition whose rows can be narrowed to one Referente, for the
 * "Commissioni configurate" card of the Referente detail (spec 0336).
 * Callers test `instanceof ReferentScopable`, never a concrete class.
 */
interface ReferentScopable
{
    /**
     * Null = no scope (the domain's own unscoped list behavior).
     */
    public function scopeToReferent(?int $referentId): void;
}
