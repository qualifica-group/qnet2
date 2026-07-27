<?php

namespace App\Migrations\Sources;

use App\DataObjects\VatRates\CreateVatRateData;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Support\ExternalApiClient;
use App\Models\VatRate;
use App\Services\VatRateService;
use RuntimeException;

/**
 * `vat-rates` migration source (spec 0013 / 0017): a plain settings lookup
 * (id, name, rate) created through VatRateService, mirroring TagsSource. An
 * independent phase-1 anchor with no cross-source reference of its own; it is
 * `products` that references it, via `vat_rate_id` remapped on `old_id`, so it
 * must be migrated before them. Re-import is idempotent (skip by old_id); the
 * name is NOT unique.
 */
class VatRatesSource extends AbstractMigrationSource
{
    public function __construct(
        ExternalApiClient $client,
        private readonly VatRateService $service,
    ) {
        parent::__construct($client);
    }

    public function key(): string
    {
        return 'vat-rates';
    }

    public function label(): string
    {
        return 'VAT rates';
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    protected function nativeColumns(): array
    {
        return [
            ['id' => 'id', 'label' => 'ID', 'type' => 'number'],
            ['id' => 'name', 'label' => 'Name', 'type' => 'string'],
            ['id' => 'rate', 'label' => 'Rate (percentage)', 'type' => 'number'],
        ];
    }

    public function endpoint(): string
    {
        return 'vat-rates';
    }

    protected function externalId(array $record): int|string|null
    {
        return $record['id'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, string|int|bool|null>
     */
    protected function mapNativeRow(array $record): array
    {
        return [
            'id' => $record['id'] ?? null,
            'name' => $record['name'] ?? null,
            'rate' => $record['rate'] ?? null,
        ];
    }

    protected function processRow(MigrationImportContext $context, array $record): MigrationRowOutcome
    {
        $externalId = $this->externalId($record);

        if ($externalId === null) {
            throw new RuntimeException('External id is required.');
        }

        if ($this->existsByOldId(VatRate::class, $externalId)) {
            return MigrationRowOutcome::skipped();
        }

        $name = trim((string) ($record['name'] ?? ''));

        if ($name === '') {
            throw new RuntimeException('name is required.');
        }

        $vatRate = $this->service->create(new CreateVatRateData(
            name: $name,
            rate: $this->mapRate($record['rate'] ?? null),
        ));

        $vatRate->old_id = $externalId;
        $vatRate->save();

        return MigrationRowOutcome::created(model: $vatRate);
    }

    /**
     * The percentage is a required, non-negative number (same rule as
     * StoreVatRateRequest). Zero is a legitimate rate (exempt), so only an
     * absent/non-numeric/negative value is a fatal per-row error — defaulting
     * it silently would invent a tax rate.
     */
    private function mapRate(mixed $externalRate): float
    {
        if (! is_numeric($externalRate)) {
            throw new RuntimeException('rate is required and must be numeric.');
        }

        $rate = (float) $externalRate;

        if ($rate < 0) {
            throw new RuntimeException('rate must be zero or greater.');
        }

        return $rate;
    }
}
