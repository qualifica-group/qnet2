<?php

declare(strict_types=1);

namespace App\Migrations\Realign;

/**
 * What a target wants the report to say about one record, beyond the field differences.
 */
final class LegacyRealignNotes
{
    /** @var list<string> */
    public array $warnings = [];

    /** @var list<string> */
    public array $configurations = [];

    public function warn(string $message): void
    {
        $this->warnings[] = $message;
    }

    /** A configuration row (status, lookup) the alignment had to create (D-16). */
    public function configurationCreated(string $name): void
    {
        $this->configurations[] = $name;
    }
}
