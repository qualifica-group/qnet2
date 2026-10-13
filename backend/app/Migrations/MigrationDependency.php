<?php

namespace App\Migrations;

/**
 * A migration source another source needs to be imported FIRST, shown to the
 * super-admin as "what this part needs to migrate well".
 *
 * REQUIRED: without it the rows of the dependent source fail or are skipped
 * (a mandatory parent, or the only records a linking source links). OPTIONAL:
 * the rows are still created, the reference is left empty with a warning in
 * the run report.
 */
final readonly class MigrationDependency
{
    private function __construct(
        public string $source,
        public bool $required,
    ) {}

    public static function required(string $source): self
    {
        return new self(source: $source, required: true);
    }

    public static function optional(string $source): self
    {
        return new self(source: $source, required: false);
    }

    /**
     * @return array{source: string, required: bool}
     */
    public function toArray(): array
    {
        return ['source' => $this->source, 'required' => $this->required];
    }
}
