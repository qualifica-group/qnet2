<?php

declare(strict_types=1);

namespace App\Tables;

/**
 * A table definition whose rows can be narrowed to one client (Anagrafica),
 * for the "related records" tabs of the Anagrafica detail (spec 0199).
 * Implemented by the outermost row-scope decorator of each supported domain;
 * callers test `instanceof RegistryScopable`, never a concrete class.
 */
interface RegistryScopable
{
    /**
     * Null = no scope (the domain's own unscoped list behavior).
     */
    public function scopeToRegistry(?int $registryId): void;
}
