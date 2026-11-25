<?php

declare(strict_types=1);

namespace App\Services\DocumentRequests;

use RuntimeException;

/**
 * A file of a document request cannot be served (spec 0429): 404 when it is
 * unknown, 502 when ArchivioSmart cannot deliver it. The message is user-safe.
 */
class DocumentDownloadException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message);
    }
}
