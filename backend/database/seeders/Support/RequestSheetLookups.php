<?php

namespace Database\Seeders\Support;

use App\DataObjects\PersonalData\CreateContact;
use App\DataObjects\PersonalData\CreatePersonalData;
use App\DataObjects\Referents\CreateReferentData;
use App\DataObjects\Users\ContactInput;
use App\DataObjects\Users\ProfileData;
use App\Enums\ContactTypeEnum;
use App\Enums\PersonalDataTypeEnum;
use App\Enums\ReferentContactScopeEnum;
use App\Imports\RequestManagement\RequestWorkflowStatuses;
use App\Models\City;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\PersonalData;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Referent;
use App\Models\ReferentType;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use App\Services\ReferentService;
use App\Support\ContactValueNormalizer;
use RuntimeException;

/**
 * Name -> id resolution for the request sheets seeded from the user's Excel files
 * (user directive 2026-11-07). The sheets are cleaned at the source, so every
 * name must match a record exactly: a miss is a data error and fails loudly,
 * never a silent fallback. Only the client lookup may find nothing (a new one).
 */
final class RequestSheetLookups
{
    private const string ITALY = 'IT';

    /** @var array<string, int> */
    private array $cache = [];

    /** @var array<string, ProductCategory> */
    private array $categories = [];

    public function __construct(
        private readonly RequestWorkflowStatuses $statuses,
        private readonly ReferentService $referents,
    ) {}

    public function userId(string $name): int
    {
        return $this->remember("user:{$name}", fn (): ?int => User::query()->where('name', $name)->value('id'));
    }

    public function primarySiteId(int $userId): ?int
    {
        return User::query()->with('employment.operationalSites')->find($userId)?->employment?->primary_operational_site_id;
    }

    public function sourceId(string $name): int
    {
        return $this->remember("source:{$name}", fn (): ?int => Source::query()->where('name', $name)->value('id'));
    }

    public function referentId(string $name): int
    {
        return $this->remember("referent:{$name}", fn (): ?int => Referent::query()->where('name', $name)->value('id'));
    }

    public function category(string $name): ProductCategory
    {
        return $this->categories[$name] ??= ProductCategory::query()->where('name', $name)->firstOrFail();
    }

    public function productId(int $categoryId, string $name): int
    {
        return $this->remember("product:{$categoryId}:{$name}", fn (): ?int => Product::query()
            ->where('category_id', $categoryId)->where('name', $name)->value('id'));
    }

    public function statusId(int $categoryId, int $sourceId, string $name): int
    {
        return $this->remember("status:{$categoryId}:{$sourceId}:{$name}", fn (): ?int => $this->statuses
            ->statuses($categoryId, $sourceId)->firstWhere('name', $name)?->id);
    }

    /**
     * The Sede a transferred request came from, identified by the Comune of its address.
     */
    public function siteIdInCity(string $city): int
    {
        return $this->remember("site:{$city}", function () use ($city): ?int {
            $ids = OperationalSite::query()
                ->whereHas('addresses.city', static fn ($query) => $query->where('name', $city))
                ->pluck('id');

            return $ids->count() === 1 ? (int) $ids->first() : null;
        });
    }

    public function cityId(?string $name): ?int
    {
        if ($name === null) {
            return null;
        }

        return $this->remember("city:{$name}", fn (): ?int => City::query()
            ->where('country_code', self::ITALY)->where('name', $name)->value('id'));
    }

    /**
     * The client already on file: same tax code, or one of the row's phones or its
     * email AND the row's name. A phone alone is not enough, relatives share one
     * (the sheet's Rosa Lazzaro is on the phone of an existing Giustina Lazzaro).
     *
     * @param  array<string, mixed>  $row
     */
    public function registryId(array $row): ?int
    {
        $taxCode = isset($row['tax_code']) ? ContactValueNormalizer::taxCode($row['tax_code']) : null;
        $phones = array_map(static fn (string $phone): string => ContactValueNormalizer::contact(ContactTypeEnum::Phone, $phone), $row['phones']);
        $email = isset($row['email']) ? ContactValueNormalizer::contact(ContactTypeEnum::Email, $row['email']) : null;
        $rowWords = $this->nameWords($row['company'] ?? ($row['first'] ?? '').' '.($row['last'] ?? ''));

        if ($phones === [] && $email === null && $taxCode === null) {
            return $this->registryIdByName($row);
        }

        $match = PersonalData::query()
            ->where('personable_type', (new Registry)->getMorphClass())
            ->where(static function ($query) use ($phones, $email, $taxCode): void {
                $query->whereHas('contacts', static fn ($contacts) => $contacts
                    ->where(static fn ($phone) => $phone->where('type', ContactTypeEnum::Phone->value)->whereIn('normalized_value', $phones))
                    ->when($email !== null, static fn ($either) => $either->orWhere(static fn ($mail) => $mail
                        ->where('type', ContactTypeEnum::Email->value)->where('normalized_value', $email))));

                if ($taxCode !== null) {
                    $query->orWhere('tax_code', $taxCode);
                }
            })
            ->orderBy('id')
            ->get(['personable_id', 'first_name', 'last_name', 'company_name', 'tax_code'])
            ->first(fn (PersonalData $card): bool => ($taxCode !== null && $card->tax_code === $taxCode)
                || array_diff($rowWords, $this->nameWords($card->first_name.' '.$card->last_name.' '.$card->company_name)) === []);

        return $match === null ? null : (int) $match->personable_id;
    }

    /**
     * A row with no phone, email or tax code is recognised by its exact name only
     * (company name, or first + last name): what keeps a second run from duplicating it.
     *
     * @param  array<string, mixed>  $row
     */
    private function registryIdByName(array $row): ?int
    {
        $query = PersonalData::query()->where('personable_type', (new Registry)->getMorphClass());

        $id = (isset($row['company'])
            ? $query->where('company_name', $row['company'])
            : $query->where('first_name', $row['first'] ?? null)->where('last_name', $row['last'] ?? null))
            ->orderBy('id')
            ->value('personable_id');

        return $id === null ? null : (int) $id;
    }

    /**
     * True when the client already has a request on the category: the row was
     * seeded by an earlier run, so it is not written twice.
     */
    public function hasRequestOnCategory(int $registryId, int $categoryId): bool
    {
        return Opportunity::query()
            ->where('registry_id', $registryId)
            ->whereHas('productLines', static fn ($lines) => $lines->where('product_category_id', $categoryId))
            ->exists();
    }

    /**
     * The referents the sheet names but the database lacks (reporters, commercials),
     * created once with the given referent type through the referents' own service
     * (name derived from the card).
     *
     * @param  array<string, array{0: string, 1: string}|null>  $referents  name => [first name, last name] of a
     *                                                                      person, null for a company (the name itself)
     */
    public function ensureReferents(User $actor, array $referents, string $typeName): void
    {
        $typeId = ReferentType::query()->where('name', $typeName)->value('id');

        foreach ($referents as $name => $person) {
            if (Referent::query()->where('name', $name)->exists()) {
                continue;
            }

            $card = $person === null
                ? new CreatePersonalData(PersonalDataTypeEnum::Company, companyName: $name)
                : new CreatePersonalData(PersonalDataTypeEnum::Individual, $person[0], $person[1]);

            $this->referents->create(
                $actor,
                new CreateReferentData($typeId, ReferentContactScopeEnum::Internal->value, null),
                new ProfileData($card),
            );
        }
    }

    /**
     * A company's contact person ("Referente Azienda"), created as a new referent
     * and listed among the company's referents (user decision 2026-11-07). Internal
     * and with no type, like the referents already linked to client anagrafiche.
     *
     * @param  array{first: ?string, last: ?string, title?: string, phones?: list<string>}  $person
     */
    public function addContactPerson(User $actor, int $registryId, array $person): void
    {
        $registry = Registry::query()->findOrFail($registryId);
        $name = trim(($person['first'] ?? '').' '.($person['last'] ?? ''));

        // A company already listing a referent of that name keeps it, never a twin.
        if ($registry->referents()->where('referents.name', $name)->exists()) {
            return;
        }

        $contacts = array_map(
            static fn (string $phone, int $index): ContactInput => new ContactInput(null, new CreateContact(ContactTypeEnum::Phone, $phone, null, $index === 0)),
            $person['phones'] ?? [],
            array_keys($person['phones'] ?? []),
        );

        $referent = $this->referents->create(
            $actor,
            new CreateReferentData(null, ReferentContactScopeEnum::Internal->value, $person['title'] ?? null),
            new ProfileData(new CreatePersonalData(PersonalDataTypeEnum::Individual, $person['first'], $person['last']), $contacts === [] ? null : $contacts),
        );

        $registry->referents()->syncWithoutDetaching([$referent->id]);
    }

    /**
     * @return list<string>
     */
    private function nameWords(string $name): array
    {
        return preg_split('/[^\p{L}]+/u', mb_strtolower($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * @param  callable(): (int|null)  $resolve
     */
    private function remember(string $key, callable $resolve): int
    {
        if (! isset($this->cache[$key])) {
            $id = $resolve();

            if ($id === null) {
                throw new RuntimeException("Request sheet: no record matches \"{$key}\".");
            }

            $this->cache[$key] = (int) $id;
        }

        return $this->cache[$key];
    }
}
