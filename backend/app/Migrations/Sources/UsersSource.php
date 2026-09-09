<?php

namespace App\Migrations\Sources;

use App\DataObjects\PersonalData\CreatePersonalData;
use App\DataObjects\Users\CreateUserData;
use App\DataObjects\Users\ProfileData;
use App\Enums\PersonalDataTypeEnum;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Sources\Concerns\MapsExternalUserRecord;
use App\Migrations\Support\ExternalApiClient;
use App\Migrations\Support\MigrationGeoResolver;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * `users` migration source (spec 0013 AC-008/009, extended for the full
 * profile): creates the account, its personal-data card, primary address and
 * contacts, its role remap, and its employment profile — all through the
 * SAME UserService::create() the Users module itself uses (single creation
 * path, ADR 0012/0013/0015). Every relational reference (roles, manager,
 * company, operational site) is an EXTERNAL id remapped via `old_id`; an
 * unresolved reference is a non-fatal warning, never fatal.
 *
 * The external system already sends a bcrypt HASH (never a plaintext
 * password): `CreateUserData` gets a throwaway random password so
 * UserService::create() satisfies its own invariants, then the row's
 * transaction (importRow()) overwrites `users.password` with the external
 * hash directly via the query builder — bypassing the model's `hashed` cast,
 * which would otherwise re-hash (and so invalidate) an already-hashed value.
 */
class UsersSource extends AbstractMigrationSource
{
    use MapsExternalUserRecord;

    private const string DEFAULT_LOCALE = 'it';

    /**
     * `$2a$`/`$2b$`/`$2y$`, 2-digit cost, 53-char hash+salt — the standard
     * bcrypt shape (Laravel's own `Hash::make()` output), asserted so a
     * malformed/plaintext value is rejected rather than stored as-is.
     */
    private const string BCRYPT_PATTERN = '/^\$2[aby]\$\d{2}\$.{53}$/';

    public function __construct(
        ExternalApiClient $client,
        private readonly UserService $service,
        private readonly MigrationGeoResolver $geoResolver,
    ) {
        parent::__construct($client);
    }

    public function key(): string
    {
        return 'users';
    }

    public function label(): string
    {
        return 'Users';
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    protected function nativeColumns(): array
    {
        return [
            ['id' => 'id', 'label' => 'ID', 'type' => 'number'],
            ['id' => 'email', 'label' => 'Email', 'type' => 'string'],
            ['id' => 'password', 'label' => 'Password (bcrypt hash)', 'type' => 'string'],
            ['id' => 'first_name', 'label' => 'First name', 'type' => 'string'],
            ['id' => 'last_name', 'label' => 'Last name', 'type' => 'string'],
            ['id' => 'tax_code', 'label' => 'Tax code', 'type' => 'string'],
            ['id' => 'vat_number', 'label' => 'VAT number', 'type' => 'string'],
            ['id' => 'birth_date', 'label' => 'Birth date', 'type' => 'date'],
            ['id' => 'country', 'label' => 'Country', 'type' => 'string'],
            ['id' => 'region', 'label' => 'Region', 'type' => 'string'],
            ['id' => 'province', 'label' => 'Province', 'type' => 'string'],
            ['id' => 'city', 'label' => 'City', 'type' => 'string'],
            ['id' => 'street', 'label' => 'Street', 'type' => 'string'],
            ['id' => 'postal_code', 'label' => 'Postal code', 'type' => 'string'],
            ['id' => 'personal_email', 'label' => 'Personal email', 'type' => 'string'],
            ['id' => 'business_phone', 'label' => 'Business phone', 'type' => 'string'],
            ['id' => 'personal_phone', 'label' => 'Personal phone', 'type' => 'string'],
            ['id' => 'is_active', 'label' => 'Is active', 'type' => 'boolean'],
            ['id' => 'is_manager', 'label' => 'Is manager', 'type' => 'boolean'],
            ['id' => 'job_description', 'label' => 'Job description', 'type' => 'string'],
            ['id' => 'reports_to_id', 'label' => 'Reports to (external id)', 'type' => 'number'],
            ['id' => 'relationship_type', 'label' => 'Relationship type', 'type' => 'string'],
            ['id' => 'company_id', 'label' => 'Company (external id)', 'type' => 'number'],
            ['id' => 'operational_site_id', 'label' => 'Operational site (external id)', 'type' => 'number'],
            ['id' => 'qualification_type', 'label' => 'Qualification type', 'type' => 'string'],
            ['id' => 'hired_at', 'label' => 'Hired at', 'type' => 'date'],
            ['id' => 'terminated_at', 'label' => 'Terminated at', 'type' => 'date'],
            ['id' => 'standard_daily_minutes', 'label' => 'Standard daily minutes', 'type' => 'number'],
            ['id' => 'break_daily_minutes', 'label' => 'Break daily minutes', 'type' => 'number'],
        ];
    }

    /**
     * The generic template is built from the scalar `columns()`; the `roles`
     * relation — `{id, name}` objects remapped to qnet roles via `old_id` — is
     * not a scalar preview column, so it is injected here to keep the copyable
     * "expected response" faithful to the real external contract.
     *
     * @return array{items: array<int, array<string, mixed>>, pagination: array{total: int, offset: int, limit: int, total_pages: int}}
     */
    public function sampleResponse(): array
    {
        $sample = parent::sampleResponse();
        $sample['items'][0]['roles'] = [['id' => 1, 'name' => 'Role name']];

        return $sample;
    }

    public function endpoint(): string
    {
        return 'users';
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
        $row = [];

        foreach ($this->nativeColumns() as $column) {
            $row[$column['id']] = $record[$column['id']] ?? null;
        }

        return $row;
    }

    protected function processRow(MigrationImportContext $context, array $record): MigrationRowOutcome
    {
        $externalId = $this->externalId($record);

        if ($externalId === null) {
            throw new RuntimeException('External id is required.');
        }

        if ($this->existsByOldId(User::class, $externalId)) {
            return MigrationRowOutcome::skipped($this->reconcileEmployment($externalId, $record));
        }

        $email = trim((string) ($record['email'] ?? ''));
        $firstName = trim((string) ($record['first_name'] ?? ''));
        $lastName = trim((string) ($record['last_name'] ?? ''));
        $externalHash = (string) ($record['password'] ?? '');

        if ($email === '' || $firstName === '' || $lastName === '') {
            throw new RuntimeException('email, first_name and last_name are required.');
        }

        if (! preg_match(self::BCRYPT_PATTERN, $externalHash)) {
            throw new RuntimeException('password must already be a valid bcrypt hash.');
        }

        $warnings = [];

        [$roleNames, $roleWarnings] = $this->resolveRoleNames($this->externalRoleIds($record));
        [$addressInput, $addressWarnings] = $this->buildAddress($record);
        [$contactInputs, $contactWarnings] = $this->buildContacts($record);
        [$employment, $employmentWarnings] = $this->buildEmployment($record);
        array_push($warnings, ...$roleWarnings, ...$addressWarnings, ...$contactWarnings, ...$employmentWarnings);

        $user = $this->service->create(
            $context->actor,
            new CreateUserData(
                email: $email,
                locale: self::DEFAULT_LOCALE,
                password: Str::password(24),
                is_active: $this->resolveActive($record),
                roles: $roleNames === [] ? null : $roleNames,
            ),
            new ProfileData(
                card: new CreatePersonalData(
                    type: PersonalDataTypeEnum::Individual,
                    firstName: $firstName,
                    lastName: $lastName,
                    taxCode: $this->blankToNull($record['tax_code'] ?? null),
                    vatNumber: $this->blankToNull($record['vat_number'] ?? null),
                    birthDate: $this->blankToNull($record['birth_date'] ?? null),
                ),
                contacts: $contactInputs === [] ? null : $contactInputs,
                addresses: $addressInput === null ? null : [$addressInput],
            ),
            $employment,
        );

        // The external hash is already bcrypt: writing it through the model's
        // `hashed` cast would re-hash (and invalidate) it, so it is set
        // directly on the column, inside the caller's per-row transaction.
        DB::table('users')->where('id', $user->id)->update(['password' => $externalHash]);

        $user->old_id = $externalId;
        $user->save();

        return MigrationRowOutcome::created($warnings, $user);
    }

    /**
     * Second pass over every imported user: back-fill any employment relation
     * (notably the self-referential `reports_to_id`) left null because the
     * referenced user was processed later in the SAME run. Each relink is
     * isolated in its own transaction so a single failure never aborts the
     * pass; only successful relinks are appended to the run report.
     */
    protected function afterImport(MigrationImportContext $context): void
    {
        $this->eachRecord(fn (array $record): mixed => $this->relinkRow($context, $record));
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function relinkRow(MigrationImportContext $context, array $record): void
    {
        $externalId = $this->externalId($record);

        if ($externalId === null) {
            return;
        }

        try {
            $message = DB::transaction(fn (): ?string => $this->relinkEmployment($externalId, $record));

            if ($message !== null) {
                $this->appendReport($context->run, $externalId, 'warning', $message);
            }
        } catch (Throwable $exception) {
            $this->appendReport($context->run, $externalId, 'warning', 'Relink pass failed: '.$exception->getMessage());
        }
    }
}
