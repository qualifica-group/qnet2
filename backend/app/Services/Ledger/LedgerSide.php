<?php

declare(strict_types=1);

namespace App\Services\Ledger;

/**
 * Which side of the ledger a section shows (spec 0266, D-5): the customer owes
 * the documents (debit) and settles them in credit, the supplier is the mirror.
 */
enum LedgerSide: string
{
    case Customer = 'customer';
    case Supplier = 'supplier';
}
