<?php

declare(strict_types=1);

namespace App\Migrations\Sources\Concerns;

use App\Migrations\AbstractMigrationSource;
use App\Models\Company;
use App\Models\CompanySite;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Required legacy references of the accounting sources (specs 0294, 0296): a
 * reference missing or not migrated fails the row, and the company site must
 * belong to the company. Text helpers shared by the same sources.
 *
 * @phpstan-require-extends AbstractMigrationSource
 */
trait RequiresLegacyAnchors
{
    /**
     * @param  class-string<Model>  $targetClass
     *
     * @throws RuntimeException when the reference is missing or not migrated
     */
    private function requiredOldId(string $targetClass, mixed $legacyId, string $label): int
    {
        if ($legacyId === null || $legacyId === '') {
            throw new RuntimeException("{$label} is required; the record is not imported.");
        }

        return $this->resolveOldId($targetClass, $legacyId)
            ?? throw new RuntimeException("{$label} (legacy id {$legacyId}) not migrated; the record is not imported.");
    }

    /**
     * The company and the company site of the record, the site belonging to the company.
     *
     * @param  array<string, mixed>  $record
     * @return array{company_id: int, company_site_id: int}
     *
     * @throws RuntimeException when either is missing, not migrated or incoherent
     */
    private function companyAndSite(array $record): array
    {
        $companyId = $this->requiredOldId(Company::class, $record['company_id'] ?? null, 'Company');
        $companySiteId = $this->requiredOldId(CompanySite::class, $record['company_site_id'] ?? null, 'Company site');

        if ((int) CompanySite::query()->whereKey($companySiteId)->value('company_id') !== $companyId) {
            throw new RuntimeException('Company site (legacy id '.$record['company_site_id'].') does not belong to the company; the record is not imported.');
        }

        return ['company_id' => $companyId, 'company_site_id' => $companySiteId];
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function cappedText(mixed $value, int $max, string $label, array &$warnings): ?string
    {
        $text = $this->blankToNull($value);

        if ($text !== null && mb_strlen($text) > $max) {
            $warnings[] = "{$label} truncated to {$max} characters.";

            return mb_substr($text, 0, $max);
        }

        return $text;
    }

    private function requiredText(mixed $value, string $field): string
    {
        return $this->blankToNull($value) ?? throw new RuntimeException("{$field} is required.");
    }

    private function blankToNull(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
