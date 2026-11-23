<?php

namespace App\Enums;

/**
 * What one ArchivioSmart call attempt left to the job that made it (spec 0410, D-11).
 */
enum ArchivioSmartCallResult
{
    /** The remote accepted the call; the record is synced. */
    case Succeeded;

    /** Transient problem: the job has already been released back to the queue. */
    case Released;

    /** Definitive problem: the record is failed and the trait's `$failureMessage` says why. */
    case Failed;
}
