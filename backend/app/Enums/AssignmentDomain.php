<?php

namespace App\Enums;

use App\RequestManagement\RequestModule;

/**
 * The record family a bulk assignment acts on (spec 0110): the import
 * wizard's staged rows, the real leads, or an offer read through one of the
 * two Quote-backed modules (spec 0130, D-9).
 *
 * The ONE dimension POST /api/assignment/selection-scope switches on —
 * which selection to read, which read gate to enforce, which requirement
 * resolver answers — so the four surfaces never spell their domain as loose
 * strings. Deliberately NOT the import registry's `{domain}` (that one names
 * the imported RESOURCE, `leads`): `import_rows` here means the staged rows
 * themselves, whatever resource they will become.
 */
enum AssignmentDomain: string
{
    case ImportRows = 'import_rows';
    case Leads = 'leads';
    case Quotes = 'quotes';
    /**
     * Spec 0130, D-9: the Enrollee "Gestione Iscritti" picker's own reading
     * of the SAME `quotes` domain — same shape, own module.
     */
    case Enrollees = 'enrollees';

    /**
     * The `RequestModule` this domain's selection is read through (spec
     * 0130, D-9) — null for the two domains with no RequestModule of their
     * own (ImportRows, Leads). The ONE place SelectionScopeController reads
     * this mapping from, so no `if ($domain === ...)` needs to live there.
     */
    public function requestModule(): ?RequestModule
    {
        return match ($this) {
            self::Quotes => RequestModule::Requests,
            self::Enrollees => RequestModule::Enrollees,
            self::ImportRows, self::Leads => null,
        };
    }
}
