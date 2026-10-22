<?php

declare(strict_types=1);

namespace App\Migrations\Sources;

use App\DataObjects\FinancialAccounts\CreateFinancialAccountData;
use App\Enums\FinancialAccountType;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationDependency;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Sources\Concerns\MapsLegacyOperationalRecord;
use App\Migrations\Support\ExternalApiClient;
use App\Models\Company;
use App\Models\FinancialAccount;
use App\Rules\ValidIban;
use App\Services\FinancialAccountService;
use Illuminate\Support\Arr;
use RuntimeException;

/**
 * `financial-accounts` migration source (spec 0297): the legacy bank accounts
 * and cash boxes, created through FinancialAccountService::create() without
 * activity log. The legacy sends the type in the qnet-2 vocabulary, the
 * normalized IBAN and the company already resolved (D-1); never card data.
 *
 * Here: `old_id` lookups, the IBAN check of qnet-2 (an invalid one is left
 * empty) and the adoption of an account created by hand with the same IBAN,
 * which takes the `old_id` instead of being duplicated (D-2).
 */
class FinancialAccountsSource extends AbstractMigrationSource
{
    use MapsLegacyOperationalRecord;

    public function __construct(
        ExternalApiClient $client,
        private readonly FinancialAccountService $accounts,
    ) {
        parent::__construct($client);
    }

    public function key(): string
    {
        return 'financial-accounts';
    }

    public function label(): string
    {
        return 'Financial accounts';
    }

    public function endpoint(): string
    {
        return 'financial-accounts';
    }

    /**
     * {@inheritDoc}
     */
    public function dependencies(): array
    {
        return [MigrationDependency::optional('companies')];
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    protected function nativeColumns(): array
    {
        return [
            ['id' => 'id', 'label' => 'ID', 'type' => 'number'],
            ['id' => 'type', 'label' => 'Type', 'type' => 'string'],
            ['id' => 'name', 'label' => 'Name', 'type' => 'string'],
            ['id' => 'company_id', 'label' => 'Company (external id)', 'type' => 'number'],
            ['id' => 'iban', 'label' => 'IBAN', 'type' => 'string'],
            ['id' => 'created_at', 'label' => 'Created at', 'type' => 'date'],
        ];
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
        return $this->legacyPreviewCells($record);
    }

    protected function processRow(MigrationImportContext $context, array $record): MigrationRowOutcome
    {
        $externalId = $this->externalId($record);

        if ($externalId === null) {
            throw new RuntimeException('External id is required.');
        }

        if ($this->existsByOldId(FinancialAccount::class, $externalId)) {
            return MigrationRowOutcome::skipped();
        }

        return activity()->withoutLogs(fn (): MigrationRowOutcome => $this->importRecord($externalId, $record));
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function importRecord(int|string $externalId, array $record): MigrationRowOutcome
    {
        $warnings = array_map('strval', (array) ($record['warnings'] ?? []));
        $type = FinancialAccountType::tryFrom((string) ($record['type'] ?? ''))
            ?? throw new RuntimeException("Type '".($record['type'] ?? '')."' is not valid; the account is not imported.");
        $iban = $this->iban($record['iban'] ?? null, $warnings);

        // Step 1: an account created by hand with the same IBAN is adopted, not duplicated (D-2).
        $adopted = $iban === null ? null : FinancialAccount::query()->whereNull('old_id')->where('iban', $iban)->first();

        if ($adopted !== null) {
            $adopted->forceFill(['old_id' => $externalId])->saveQuietly();
            $warnings[] = "IBAN already on account #{$adopted->id}: that account is linked to the legacy one.";

            return MigrationRowOutcome::created($warnings, $adopted);
        }

        // Step 2: create it through the service, only the columns of its type.
        $attributes = Arr::only([
            'name' => trim((string) ($record['name'] ?? '')) ?: throw new RuntimeException('name is required.'),
            'company_id' => $this->remapLegacyId(Company::class, $record['company_id'] ?? null, 'company_id', $warnings),
            'iban' => $iban,
            'account_number' => $this->blankToNull($record['account_number'] ?? null),
            'address_line' => $this->blankToNull($record['address_line'] ?? null),
            'postal_code' => $this->blankToNull($record['postal_code'] ?? null),
            'notes' => $this->blankToNull($record['notes'] ?? null),
        ], $type->fields());
        $account = $this->accounts->create(new CreateFinancialAccountData($type, $attributes));

        // Step 3: the legacy anchors.
        $this->finalizeLegacyRecord($account, $externalId, $record);

        return MigrationRowOutcome::created($warnings, $account);
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function iban(mixed $value, array &$warnings): ?string
    {
        $iban = $this->blankToNull($value);

        if ($iban === null) {
            return null;
        }

        $iban = ValidIban::normalize($iban);

        if (! ValidIban::isValid($iban)) {
            $warnings[] = "IBAN '{$iban}' is not valid, left empty.";

            return null;
        }

        return $iban;
    }

    private function blankToNull(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
