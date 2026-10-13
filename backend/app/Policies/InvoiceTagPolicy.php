<?php

declare(strict_types=1);

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `invoice-tags` resource (spec 0247): every
 * ability maps to "invoice-tags.{ability}" via BasePolicy.
 */
class InvoiceTagPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'invoice-tags';
    }
}
