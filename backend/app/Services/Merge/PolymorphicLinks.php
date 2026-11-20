<?php

namespace App\Services\Merge;

/**
 * The polymorphic columns that can point at a registry or referent (spec 0394,
 * D-8): the commission recipients and the audit log subject.
 */
final class PolymorphicLinks
{
    /** @var list<string> */
    private const array COMMISSION_TABLES = ['commission_configurations', 'commission_accruals', 'quote_line_commissions'];

    /**
     * @return list<LinkedSource>
     */
    public static function for(string $entityAlias): array
    {
        return array_map(
            static fn (string $table): LinkedSource => new LinkedSource('commissions', $table, ["{$table}.recipient_id"], typeColumn: "{$table}.recipient_type", typeAlias: $entityAlias),
            self::COMMISSION_TABLES,
        );
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    public static function polymorphicReferences(): array
    {
        return [
            ...array_map(static fn (string $table): array => [$table, 'recipient_type', 'recipient_id'], self::COMMISSION_TABLES),
            ['activity_log', 'subject_type', 'subject_id'],
        ];
    }
}
