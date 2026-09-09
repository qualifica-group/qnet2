<?php

namespace App\Enums;

/**
 * The record family a bulk assignment acts on (spec 0110): the import
 * wizard's staged rows, the real leads, or Gestione richieste's offers.
 *
 * The ONE dimension POST /api/assignment/required-categories switches on —
 * which selection to read, which read gate to enforce, which requirement
 * resolver answers — so the three surfaces never spell their domain as loose
 * strings. Deliberately NOT the import registry's `{domain}` (that one names
 * the imported RESOURCE, `leads`): `import_rows` here means the staged rows
 * themselves, whatever resource they will become.
 */
enum AssignmentDomain: string
{
    case ImportRows = 'import_rows';
    case Leads = 'leads';
    case Quotes = 'quotes';
}
