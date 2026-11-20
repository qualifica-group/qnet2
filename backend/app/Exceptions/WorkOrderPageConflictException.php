<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * The page was saved by someone else since the client read it (spec 0420, D-3).
 * The controller answers 409 `page_version_conflict` with the current page.
 */
final class WorkOrderPageConflictException extends RuntimeException
{
    public const string CODE = 'page_version_conflict';

    public function __construct()
    {
        parent::__construct('The page was modified by someone else.');
    }
}
