<?php

namespace App\Migrations\Sources;

use App\DataObjects\PersonalData\CreatePersonalData;
use App\DataObjects\Referents\CreateReferentData;
use App\DataObjects\Users\ContactInput;
use App\DataObjects\Users\ProfileData;
use App\Enums\ContactTypeEnum;
use App\Enums\GenderEnum;
use App\Enums\PersonalDataTypeEnum;
use App\Enums\ReferentContactScopeEnum;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Sources\Concerns\MapsExternalProfileRecord;
use App\Migrations\Support\ExternalApiClient;
use App\Migrations\Support\MigrationGeoResolver;
use App\Models\Referent;
use App\Models\ReferentType;
use App\Services\ReferentService;
use RuntimeException;

/**
 * `referents` migration source (spec 0013 / 0016): creates the referent, its
 * personal-data card, primary address and contacts — all through the SAME
 * ReferentService::create() the Referents module itself uses (single creation
 * path). Contacts and addresses are mapped exactly like UsersSource via the
 * shared MapsExternalProfileRecord. The only relational reference,
 * `referent_type_id`, is an EXTERNAL id remapped via `old_id`; an unresolved
 * reference is a non-fatal warning (the referent is created without a type).
 */
class ReferentsSource extends AbstractMigrationSource
{
    use MapsExternalProfileRecord;

    private const string CARD_TYPE = PersonalDataTypeEnum::Individual->value;

    public function __construct(
        ExternalApiClient $client,
        private readonly ReferentService $service,
        private readonly MigrationGeoResolver $geoResolver,
    ) {
        parent::__construct($client);
    }

    public function key(): string
    {
        return 'referents';
    }

    public function label(): string
    {
        return 'Referents';
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    protected function nativeColumns(): array
    {
        return [
            ['id' => 'id', 'label' => 'ID', 'type' => 'number'],
            ['id' => 'referent_type_id', 'label' => 'Referent type (external id)', 'type' => 'number'],
            ['id' => 'contact_scope', 'label' => 'Contact scope', 'type' => 'string'],
            ['id' => 'first_name', 'label' => 'First name', 'type' => 'string'],
            ['id' => 'last_name', 'label' => 'Last name', 'type' => 'string'],
            ['id' => 'tax_code', 'label' => 'Tax code', 'type' => 'string'],
            ['id' => 'vat_number', 'label' => 'VAT number', 'type' => 'string'],
            ['id' => 'birth_date', 'label' => 'Birth date', 'type' => 'date'],
            ['id' => 'gender', 'label' => 'Gender', 'type' => 'string'],
            ['id' => 'notes', 'label' => 'Notes', 'type' => 'string'],
            ['id' => 'country', 'label' => 'Country', 'type' => 'string'],
            ['id' => 'region', 'label' => 'Region', 'type' => 'string'],
            ['id' => 'province', 'label' => 'Province', 'type' => 'string'],
            ['id' => 'city', 'label' => 'City', 'type' => 'string'],
            ['id' => 'street', 'label' => 'Street', 'type' => 'string'],
            ['id' => 'postal_code', 'label' => 'Postal code', 'type' => 'string'],
            ['id' => 'email', 'label' => 'Email', 'type' => 'string'],
            ['id' => 'pec', 'label' => 'PEC', 'type' => 'string'],
            ['id' => 'phone', 'label' => 'Phone', 'type' => 'string'],
            ['id' => 'mobile', 'label' => 'Phone 2', 'type' => 'string'],
            ['id' => 'fax', 'label' => 'Fax', 'type' => 'string'],
        ];
    }

    public function endpoint(): string
    {
        return 'referents';
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

        if ($this->existsByOldId(Referent::class, $externalId)) {
            return MigrationRowOutcome::skipped();
        }

        $firstName = trim((string) ($record['first_name'] ?? ''));
        $lastName = trim((string) ($record['last_name'] ?? ''));

        if ($firstName === '' || $lastName === '') {
            throw new RuntimeException('first_name and last_name are required.');
        }

        $warnings = [];

        $referentTypeId = $this->resolveReferentType($record['referent_type_id'] ?? null, $warnings);
        $contactScope = $this->resolveContactScope($record['contact_scope'] ?? null, $warnings);
        $gender = $this->resolveGender($record['gender'] ?? null, $warnings);
        [$addressInput, $addressWarnings] = $this->buildAddress($record);
        [$contactInputs, $contactWarnings] = $this->buildContacts($record);
        array_push($warnings, ...$addressWarnings, ...$contactWarnings);

        $referent = $this->service->create(
            $context->actor,
            new CreateReferentData(
                referentTypeId: $referentTypeId,
                contactScope: $contactScope,
                notes: $this->blankToNull($record['notes'] ?? null),
            ),
            new ProfileData(
                card: new CreatePersonalData(
                    type: PersonalDataTypeEnum::from(self::CARD_TYPE),
                    firstName: $firstName,
                    lastName: $lastName,
                    taxCode: $this->blankToNull($record['tax_code'] ?? null),
                    vatNumber: $this->blankToNull($record['vat_number'] ?? null),
                    birthDate: $this->blankToNull($record['birth_date'] ?? null),
                    gender: $gender,
                ),
                contacts: $contactInputs === [] ? null : $contactInputs,
                addresses: $addressInput === null ? null : [$addressInput],
            ),
        );

        $referent->old_id = $externalId;
        $referent->save();

        return MigrationRowOutcome::created($warnings, $referent);
    }

    /**
     * The referent's contact channels: email, PEC, phone and fax, each flagged
     * primary. The external `mobile` field is stored as a second `phone` AFTER
     * the landline, so the one-primary-per-type invariant keeps it primary
     * (spec 0139 D-7). Delegates to the
     * shared candidate-driven builder.
     *
     * @param  array<string, mixed>  $record
     * @return array{0: array<int, ContactInput>, 1: array<int, string>}
     */
    private function buildContacts(array $record): array
    {
        return $this->buildContactInputs($record, [
            ['field' => 'email', 'type' => ContactTypeEnum::Email, 'label' => 'Email'],
            ['field' => 'pec', 'type' => ContactTypeEnum::Pec, 'label' => 'PEC'],
            ['field' => 'phone', 'type' => ContactTypeEnum::Phone, 'label' => 'Telefono'],
            ['field' => 'mobile', 'type' => ContactTypeEnum::Phone, 'label' => null],
            ['field' => 'fax', 'type' => ContactTypeEnum::Fax, 'label' => 'Fax'],
        ]);
    }

    /**
     * Map the external sex onto GenderEnum. A referent is always an individual
     * card, so an absent/blank value falls back to the enum default (male); an
     * unknown value is a non-fatal warning and likewise falls back, so the row
     * never fails on gender alone.
     *
     * @param  array<int, string>  $warnings
     */
    private function resolveGender(mixed $raw, array &$warnings): string
    {
        $default = (GenderEnum::default() ?? GenderEnum::Male)->value;
        $value = trim((string) ($raw ?? ''));

        if ($value === '') {
            return $default;
        }

        $case = GenderEnum::tryFrom($value);

        if ($case === null) {
            $warnings[] = "Unresolved gender '{$value}', defaulted to {$default}.";

            return $default;
        }

        return $case->value;
    }

    /**
     * Remap the external referent-type reference to the qnet type id via
     * `old_id`. Absent/blank -> null (no type, no warning); a reference that
     * resolves to no migrated type -> non-fatal warning, referent created
     * without a type.
     *
     * @param  array<int, string>  $warnings
     */
    private function resolveReferentType(mixed $externalRef, array &$warnings): ?int
    {
        if ($externalRef === null || $externalRef === '') {
            return null;
        }

        $id = $this->resolveOldId(ReferentType::class, $externalRef);

        if ($id === null) {
            $warnings[] = "Unresolved referent_type_id (external id {$externalRef}).";
        }

        return $id;
    }

    /**
     * Map the external contact scope onto ReferentContactScopeEnum. Absent/
     * blank falls back to the enum default; an unknown value is a non-fatal
     * warning and likewise falls back, so the row never fails on scope alone.
     *
     * @param  array<int, string>  $warnings
     */
    private function resolveContactScope(mixed $raw, array &$warnings): string
    {
        $default = (ReferentContactScopeEnum::default() ?? ReferentContactScopeEnum::Internal)->value;
        $value = trim((string) ($raw ?? ''));

        if ($value === '') {
            return $default;
        }

        $case = ReferentContactScopeEnum::tryFrom($value);

        if ($case === null) {
            $warnings[] = "Unresolved contact_scope '{$value}', defaulted to {$default}.";

            return $default;
        }

        return $case->value;
    }
}
