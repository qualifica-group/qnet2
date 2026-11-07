<?php

namespace Database\Seeders\Support;

use App\DataObjects\PersonalData\CreateContact;
use App\DataObjects\PersonalData\CreatePersonalData;
use App\DataObjects\RequestManagement\CreateRequestData;
use App\DataObjects\Users\ContactInput;
use App\DataObjects\Users\ProfileData;
use App\Enums\CategoryManagementMode;
use App\Enums\ContactTypeEnum;
use App\Enums\PersonalDataTypeEnum;
use App\Models\Opportunity;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\User;
use App\Services\Opportunities\OpportunityProductLineCoverage;
use App\Services\Opportunities\RegistryOpenOpportunityGuard;
use App\Services\Quotes\ProductOfferLineResolver;
use App\Services\RequestManagement\RequestCreationService;
use App\Support\ManagerPositions;
use Illuminate\Support\Facades\DB;

/**
 * Writes one row of a request sheet (user directive 2026-11-07) as a Gestione
 * Richieste record: the client (the one on file with the same phone/tax code, or a
 * new Anagrafica), then Opportunita + Offerta through RequestCreationService, the
 * same path the create form and the import use, never re-implemented here.
 *
 * A row marked `transferred_from` is born already transferred: the Offerta keeps
 * the operator's Sede and records the origin Sede with the transfer flag, plus the
 * same explicit history entry RequestTransferService writes, so the panel shows a
 * completed transfer. Its notifications are not sent: nobody is moving it now.
 * A company row (`company`) may name its contact person, created as a referent
 * of the company; `team` adds other Gestori Account beside the operator. A
 * client that already has an open opportunity gets the row as a new Offerta on it
 * (RequestSheetOfferAppender) instead of a second opportunity the rule refuses.
 */
final class RequestSheetWriter
{
    public const string CREATED = 'created';

    public const string ALREADY_PRESENT = 'already_present';

    public const string APPENDED = 'appended';

    private const string TRANSFER_LOG = 'Request management contact transfer';

    public function __construct(
        private readonly RequestCreationService $requests,
        private readonly RequestSheetLookups $lookups,
        private readonly ProductOfferLineResolver $offerLines,
        private readonly RegistryOpenOpportunityGuard $openOpportunities,
        private readonly OpportunityProductLineCoverage $coverage,
        private readonly RequestSheetOfferAppender $appender,
    ) {}

    /**
     * @param  array<string, mixed>  $row  one entry of a sheet catalogue
     * @param  array<string, string>  $attributeColumns  attribute code => row key
     */
    public function write(User $actor, ProductCategory $category, array $row, array $attributeColumns): string
    {
        return DB::transaction(function () use ($actor, $category, $row, $attributeColumns): string {
            // Step 1: the client, and the guard against a second run.
            $registryId = $this->lookups->registryId($row);

            if ($registryId !== null && $this->lookups->hasRequestOnCategory($registryId, $category->id)) {
                return self::ALREADY_PRESENT;
            }

            // Step 2: a client with an open opportunity gets the Offerta on it.
            $open = $this->blockingOpportunity($registryId, $category);

            if ($open !== null) {
                $this->appender->append($actor, $open, $category, $row, $this->references($row), $this->teamSlots($this->lookups->userId($row['operator']), $row['team'] ?? []));

                if (isset($row['contact_person'])) {
                    $this->lookups->addContactPerson($actor, $registryId, $row['contact_person']);
                }

                return self::APPENDED;
            }

            // Step 3: Opportunita + Offerta.
            $quote = $this->requests->create($actor, $this->requestData($category, $row, $registryId, $attributeColumns))['quote'];

            // Step 4: the transfer the sheet reports as already done.
            if (isset($row['transferred_from'])) {
                $this->markTransferred($quote, $actor, $this->lookups->siteIdInCity($row['transferred_from']));
            }

            // Step 5: the company's contact person, among its referents.
            if (isset($row['contact_person'])) {
                $this->lookups->addContactPerson($actor, $quote->opportunity->registry_id, $row['contact_person']);
            }

            return self::CREATED;
        });
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, string>  $attributeColumns
     */
    private function requestData(ProductCategory $category, array $row, ?int $registryId, array $attributeColumns): CreateRequestData
    {
        $operatorId = $this->lookups->userId($row['operator']);
        $refs = $this->references($row);

        return new CreateRequestData(
            registryId: $registryId,
            clientProfile: $registryId === null ? $this->profile($row) : null,
            productLines: [['product_category_id' => $category->id]],
            sourceId: $refs['source_id'],
            reporterId: $refs['reporter_id'],
            commercialId: $refs['commercial_id'],
            managerSlots: $this->teamSlots($operatorId, $row['team'] ?? []),
            operationalSiteId: $refs['operational_site_id'],
            nextCallbackAt: isset($row['callback']) ? $row['callback'].' '.config('imports.request_management.callback_time') : null,
            generalNotes: $row['notes'] ?? null,
            attributeValues: $this->attributeValues($row, $attributeColumns),
            offerLines: isset($row['product']) ? $this->offerLines->resolve([$this->lookups->productId($category->id, $row['product'])]) : null,
            workflowStatusId: $this->lookups->statusId($category->id, $refs['source_id'], $row['status']),
        );
    }

    /**
     * The open opportunity that would refuse a new one for the client (the one
     * per anagrafica rule); none for a new client or a single-mode category,
     * which the rule lets through.
     */
    private function blockingOpportunity(?int $registryId, ProductCategory $category): ?Opportunity
    {
        if ($registryId === null || $this->coverage->managementModeOfCategories([$category->id]) === CategoryManagementMode::Single) {
            return null;
        }

        return $this->openOpportunities->openOpportunityFor($registryId);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{source_id: int, reporter_id: ?int, commercial_id: ?int, operational_site_id: ?int}
     */
    private function references(array $row): array
    {
        return [
            'source_id' => $this->lookups->sourceId($row['source']),
            'reporter_id' => isset($row['reporter']) ? $this->lookups->referentId($row['reporter']) : null,
            'commercial_id' => isset($row['commercial']) ? $this->lookups->referentId($row['commercial']) : null,
            'operational_site_id' => $this->lookups->primarySiteId($this->lookups->userId($row['operator'])),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function profile(array $row): ProfileData
    {
        $contacts = [];

        foreach ($row['phones'] as $index => $phone) {
            $contacts[] = new ContactInput(null, new CreateContact(ContactTypeEnum::Phone, $phone, null, $index === 0));
        }

        if (isset($row['email'])) {
            $contacts[] = new ContactInput(null, new CreateContact(ContactTypeEnum::Email, $row['email'], null, true));
        }

        return new ProfileData(
            card: new CreatePersonalData(
                type: isset($row['company']) ? PersonalDataTypeEnum::Company : PersonalDataTypeEnum::Individual,
                firstName: $row['first'] ?? null,
                lastName: $row['last'] ?? null,
                companyName: $row['company'] ?? null,
                taxCode: $row['tax_code'] ?? null,
                birthDate: $row['birth_date'] ?? null,
                residenceCityId: $this->lookups->cityId($row['city'] ?? null),
            ),
            contacts: $contacts,
        );
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, string>  $attributeColumns
     * @return array<string, mixed>|null
     */
    private function attributeValues(array $row, array $attributeColumns): ?array
    {
        $values = [];

        foreach ($attributeColumns as $code => $key) {
            if (isset($row[$key])) {
                $values[$code] = $row[$key];
            }
        }

        return $values === [] ? null : $values;
    }

    /**
     * @return array<int, int|null>
     */
    /**
     * The Offerta's team: the operator in its slot, plus the other Gestori Account
     * the sheet names (`team`: position => user name, e.g. 5 = Operatore APL). A
     * manager holds one slot only, so one already the operator is not repeated.
     *
     * @param  array<int, string>  $team
     * @return array<int, int|null>
     */
    private function teamSlots(int $operatorId, array $team): array
    {
        $slots = array_fill(0, max([ManagerPositions::OPERATOR, ...array_keys($team)]), null);
        $slots[ManagerPositions::OPERATOR - 1] = $operatorId;

        foreach ($team as $position => $name) {
            $userId = $this->lookups->userId($name);

            if ($userId !== $operatorId) {
                $slots[$position - 1] = $userId;
            }
        }

        return $slots;
    }

    /**
     * Same columns and history entry as RequestTransferService::transferOne(): the
     * request already sits on its destination Sede with its operator, so only the
     * origin and the flag are written.
     */
    private function markTransferred(Quote $quote, User $actor, int $originSiteId): void
    {
        $quote->disableLogging();
        $quote->is_transferred = true;
        $quote->transferred_from_operational_site_id = $originSiteId;
        $quote->save();

        activity($quote->opportunity->getTable())
            ->performedOn($quote->opportunity)
            ->causedBy($actor)
            ->event('updated')
            ->withProperties([
                'attributes' => ['operational_site_id' => $quote->operational_site_id, 'is_transferred' => true, 'operator_id' => $quote->operator_id],
                'old' => ['operational_site_id' => $originSiteId, 'is_transferred' => false, 'operator_id' => $quote->operator_id],
            ])
            ->log(self::TRANSFER_LOG);
    }
}
