<?php

namespace App\Migrations\Sources;

use App\DataObjects\PersonalData\CreatePersonalData;
use App\DataObjects\Users\CreateUserData;
use App\DataObjects\Users\ProfileData;
use App\Enums\PersonalDataTypeEnum;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationDependency;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Sources\Concerns\MapsExternalUserRecord;
use App\Migrations\Support\ExistingUserMatcher;
use App\Migrations\Support\ExternalApiClient;
use App\Migrations\Support\MigrationGeoResolver;
use App\Models\EmploymentProfile;
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
 *
 * A legacy user that already exists in qnet (same email, or same name when not
 * linked yet: ExistingUserMatcher) is ADOPTED instead of created: it gets the
 * `old_id` and only its empty employment references are filled. An active
 * legacy user that matches nobody is created with "[OLD] " before its first name.
 *
 * Link-only mode (`migrations.users_link_only`, spec 0285): for an installation
 * whose accounts come from its own seeder, a matched user only gets its
 * `old_id` and nothing else is written on it; an unmatched one is created
 * as in the full import, but always disabled.
 */
class UsersSource extends AbstractMigrationSource
{
    use MapsExternalUserRecord;

    private const string DEFAULT_LOCALE = 'it';

    /**
     * Prefixed to the first name of an ACTIVE legacy user no qnet account
     * matched, so the separate account it lands on reads as the legacy one,
     * like the "[OLD]" catalogue (user decision 2026-10-14). Only qnet knows
     * whether the match succeeded, so the mark is set here, not by the legacy.
     */
    private const string UNMATCHED_NAME_MARK = '[OLD] ';

    /**
     * `$2a$`/`$2b$`/`$2y$`, 2-digit cost, 53-char hash+salt — the standard
     * bcrypt shape (Laravel's own `Hash::make()` output), asserted so a
     * malformed/plaintext value is rejected rather than stored as-is.
     */
    private const string BCRYPT_PATTERN = '/^\$2[aby]\$\d{2}\$.{53}$/';

    /**
     * Legacy ids of the users link-only mode created in the current run: the
     * only ones the relinking pass may touch, a matched user keeps nothing but
     * its `old_id` (spec 0285 D-3).
     *
     * @var array<int|string, true>
     */
    private array $createdInLinkOnly = [];

    public function __construct(
        ExternalApiClient $client,
        private readonly UserService $service,
        private readonly MigrationGeoResolver $geoResolver,
        private readonly ExistingUserMatcher $matcher,
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
     * {@inheritDoc}
     */
    public function dependencies(): array
    {
        return [
            MigrationDependency::optional('roles'),
            MigrationDependency::optional('companies'),
            MigrationDependency::optional('operational-sites'),
        ];
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
            ['id' => 'reports_to_id', 'label' => 'Reports to (external id)', 'type' => 'number'],
            ['id' => 'relationship_type', 'label' => 'Relationship type', 'type' => 'string'],
            ['id' => 'company_id', 'label' => 'Company (external id)', 'type' => 'number'],
            ['id' => 'operational_site_id', 'label' => 'Operational site (external id)', 'type' => 'number'],
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

        if ($this->linkOnly()) {
            return $this->linkRow($context, $externalId, $record);
        }

        if ($this->existsByOldId(User::class, $externalId)) {
            return MigrationRowOutcome::skipped($this->reconcileEmployment($externalId, $record));
        }

        [$email, $firstName, $lastName] = $this->identity($record);
        $existing = $this->matcher->match($email, $firstName, $lastName);

        if ($existing !== null) {
            return $this->adopt($existing, $externalId, $record);
        }

        return $this->create($context, $externalId, $record);
    }

    /**
     * A legacy user no qnet account matched becomes a new account through
     * UserService::create(), with the legacy bcrypt hash as its password.
     * `$disabled` overrides the legacy state of the account, never the
     * "[OLD] " mark, which still follows it.
     *
     * @param  array<string, mixed>  $record
     * @param  array<int, string>  $warnings
     */
    private function create(MigrationImportContext $context, int|string $externalId, array $record, array $warnings = [], bool $disabled = false): MigrationRowOutcome
    {
        [$email, $firstName, $lastName] = $this->identity($record);
        $externalHash = (string) ($record['password'] ?? '');

        if (! preg_match(self::BCRYPT_PATTERN, $externalHash)) {
            throw new RuntimeException('password must already be a valid bcrypt hash.');
        }

        $isActive = $this->resolveActive($record);

        [$roleNames, $roleWarnings] = $this->resolveRoleNames($this->externalRoleIds($record));
        [$addressInput, $addressWarnings] = $this->buildAddress($record);
        [$contactInputs, $contactWarnings] = $this->buildContacts($record);
        [$employment, $employmentWarnings] = $this->buildEmployment($record);
        [$initialCondition, $conditionWarnings] = $this->buildInitialCondition($record);
        array_push($warnings, ...$roleWarnings, ...$addressWarnings, ...$contactWarnings, ...$employmentWarnings, ...$conditionWarnings);

        $user = $this->service->create(
            $context->actor,
            new CreateUserData(
                email: $email,
                locale: self::DEFAULT_LOCALE,
                password: Str::password(24),
                is_active: $isActive && ! $disabled,
                roles: $roleNames === [] ? null : $roleNames,
            ),
            new ProfileData(
                card: new CreatePersonalData(
                    type: PersonalDataTypeEnum::Individual,
                    firstName: $isActive ? self::UNMATCHED_NAME_MARK.$firstName : $firstName,
                    lastName: $lastName,
                    taxCode: $this->blankToNull($record['tax_code'] ?? null),
                    vatNumber: $this->blankToNull($record['vat_number'] ?? null),
                    birthDate: $this->blankToNull($record['birth_date'] ?? null),
                ),
                contacts: $contactInputs === [] ? null : $contactInputs,
                addresses: $addressInput === null ? null : [$addressInput],
            ),
            $employment,
            // Spec 0271 D-6 applies to the API only: the import writes as-is.
            enforceCoverage: false,
        );

        // The external hash is already bcrypt: writing it through the model's
        // `hashed` cast would re-hash (and invalidate) it, so it is set
        // directly on the column, inside the caller's per-row transaction.
        DB::table('users')->where('id', $user->id)->update(['password' => $externalHash]);

        $user->old_id = $externalId;
        $user->save();

        // Spec 0272 D-9: relationship type and company become the first contract condition.
        $profile = EmploymentProfile::query()->where('user_id', $user->id)->first();

        if ($profile !== null) {
            $this->applyInitialCondition($profile, $initialCondition);
        }

        return MigrationRowOutcome::created($warnings, $user);
    }

    private function linkOnly(): bool
    {
        return (bool) config('migrations.users_link_only', false);
    }

    /**
     * Link-only mode (spec 0285 D-3/D-6): the matched qnet user gets the
     * `old_id` and nothing else; an unmatched record is created as in the full
     * import, disabled: the installation's own accounts are the ones in use.
     * A matcher conflict still throws, so the row fails as in the full import.
     *
     * @param  array<string, mixed>  $record
     */
    private function linkRow(MigrationImportContext $context, int|string $externalId, array $record): MigrationRowOutcome
    {
        if ($this->existsByOldId(User::class, $externalId)) {
            return MigrationRowOutcome::skipped();
        }

        [$email, $firstName, $lastName] = $this->identity($record);
        $existing = $this->matcher->match($email, $firstName, $lastName);

        if ($existing === null) {
            $outcome = $this->create($context, $externalId, $record, ["No qnet user matches {$email}: created disabled from the legacy record (link-only mode)."], disabled: true);
            $this->createdInLinkOnly[$externalId] = true;

            return $outcome;
        }

        $existing->old_id = $externalId;
        $existing->save();

        return MigrationRowOutcome::created(["Existing user {$existing->email} linked to this legacy record (link-only mode)."]);
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array{0: string, 1: string, 2: string} email, first name, last name
     */
    private function identity(array $record): array
    {
        $email = trim((string) ($record['email'] ?? ''));
        $firstName = trim((string) ($record['first_name'] ?? ''));
        $lastName = trim((string) ($record['last_name'] ?? ''));

        if ($email === '' || $firstName === '' || $lastName === '') {
            throw new RuntimeException('email, first_name and last_name are required.');
        }

        return [$email, $firstName, $lastName];
    }

    /**
     * Link an existing qnet user to the legacy record: set its `old_id` and fill
     * only its empty employment references. Role, password, email, name and
     * contacts stay as qnet has them; no model on the outcome, so no custom
     * field value is written either.
     *
     * @param  array<string, mixed>  $record
     */
    private function adopt(User $user, int|string $externalId, array $record): MigrationRowOutcome
    {
        $user->old_id = $externalId;
        $user->save();

        return MigrationRowOutcome::created([
            "Existing user {$user->email} linked to this legacy record; only empty fields were filled.",
            ...$this->reconcileEmployment($externalId, $record),
        ]);
    }

    /**
     * Second pass over every imported user: back-fill any employment relation
     * (notably the self-referential `reports_to_id`) still unresolved because
     * the referenced user was processed later in the SAME run. Each relink is
     * isolated in its own transaction so a single failure never aborts the
     * pass; only successful relinks are appended to the run report. In
     * link-only mode only the users created by this run are relinked.
     */
    protected function afterImport(MigrationImportContext $context): void
    {
        if (! $this->linkOnly()) {
            $this->eachRecord(fn (array $record): mixed => $this->relinkRow($context, $record));

            return;
        }

        if ($this->createdInLinkOnly !== []) {
            $this->eachRecord(fn (array $record): mixed => isset($this->createdInLinkOnly[$this->externalId($record)])
                ? $this->relinkRow($context, $record)
                : null);
        }

        $this->createdInLinkOnly = [];
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
