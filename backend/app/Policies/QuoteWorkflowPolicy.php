<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `quote-workflows` resource (spec 0047; moved
 * onto the Offerta by spec 0083, D-6). No special overrides: every ability
 * maps to "quote-workflows.{ability}" via BasePolicy, auto-discovered by
 * Laravel from the QuoteWorkflow model.
 */
class QuoteWorkflowPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'quote-workflows';
    }
}
