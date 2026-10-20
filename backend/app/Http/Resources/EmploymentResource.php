<?php

namespace App\Http\Resources;

use App\Authorization\UsersAuthorization;
use App\Models\Company;
use App\Models\EmploymentContract;
use App\Models\EmploymentProfile;
use App\Models\JobRole;
use App\Models\OperationalSite;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * @mixin EmploymentProfile
 *
 * The nested `employment` object (spec 0015): scalar fields, the relation
 * ids, and each relation's {id,label} reference — emitted only when that
 * relation was eager-loaded (mirrors PersonalDataResource's `whenLoaded`
 * discipline, avoiding an N+1 per row).
 *
 * The site membership (spec 0103) is no longer a single `operational_site_id`
 * column: `primary_operational_site_id`/`remote_operational_site_ids` proxy
 * the profile's own accessors (which read off the `operationalSites` pivot
 * collection), and the two reference shapes below are derived from that SAME
 * collection — so eager-loading `operationalSites.addresses.city` covers
 * every one of the four keys with a single relation.
 *
 * The assignment competence (spec 0111) follows the same discipline:
 * `product_lines` is emitted only when `productLines` was eager-loaded. It
 * REPLACES the former `business_function_id`/`business_function` pair (D-1
 * dropped the column) and the category-only keys of spec 0110, and carries
 * the same shape every other owner of the collection emits (see
 * OpportunityResource::summarizeProductLines()) — except `product_category`
 * may legitimately be null here (spec 0129 D-3: the row covers every
 * category of its function), which summarizeByName() already handles.
 *
 * `covers_all_product_categories` (spec 0129 D-1) is a plain scalar, emitted
 * unconditionally — it needs no eager load.
 *
 * The reports-to managers (spec 0166 D-2, replacing the single
 * `reports_to_id`/`reports_to` pair) follow the same `whenLoaded` discipline
 * as every other relation here: `reports_to_ids` proxies the profile's own
 * accessor (which reads off the `reportsTo` pivot collection, ordered by
 * name), and `reports_to` is the SAME collection projected to `{id,label}`
 * references, both emitted only when `reportsTo` was eager-loaded.
 *
 * The contractual data (spec 0283 D-4) leaves the payload when the actor's
 * field matrix hides it: hiding it on the form alone would still ship it.
 */
class EmploymentResource extends JsonResource
{
    /**
     * `users` field key => the payload keys that carry its value.
     *
     * @var array<string, array<int, string>>
     */
    private const array KEYS_BY_FIELD = [
        'employment.relationship_type' => ['relationship_type'],
        'employment.company_id' => ['company_id', 'company'],
        UsersAuthorization::CONTRACTS_FIELD => ['current_contract'],
        'employment.hired_at' => ['hired_at'],
        'employment.terminated_at' => ['terminated_at'],
        'employment.standard_daily_minutes' => ['standard_daily_minutes'],
        'employment.break_daily_minutes' => ['break_daily_minutes'],
        'employment.iban' => ['iban'],
        'employment.iban_holder' => ['iban_holder'],
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return Arr::except($this->payload($request), $this->hiddenKeys($request));
    }

    /**
     * The payload keys of the fields the actor's matrix hides; all of them
     * without an actor. Visibility does not depend on the target user.
     *
     * @return array<int, string>
     */
    private function hiddenKeys(Request $request): array
    {
        $actor = $request->user();
        $fields = $actor === null ? [] : app(UsersAuthorization::class)->fieldPermissions($actor, null);

        return collect(self::KEYS_BY_FIELD)
            ->reject(static fn (array $keys, string $field): bool => $fields[$field]->visible ?? false)
            ->flatten()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        return [
            'id' => $this->id,
            'covers_all_product_categories' => $this->covers_all_product_categories,
            'is_assignable' => $this->is_assignable,
            'relationship_type' => $this->relationship_type,
            'hired_at' => $this->hired_at,
            'terminated_at' => $this->terminated_at,
            'standard_daily_minutes' => $this->standard_daily_minutes,
            'break_daily_minutes' => $this->break_daily_minutes,

            'current_contract' => $this->when($this->relationLoaded('contracts'), fn (): ?array => $this->currentContract()),
            ...$this->paymentData($request),

            'reports_to_ids' => $this->reports_to_ids,
            'job_role_ids' => $this->when($this->relationLoaded('jobRoles'), fn (): array => $this->job_role_ids),
            'job_roles' => $this->when(
                $this->relationLoaded('jobRoles'),
                fn (): array => $this->jobRoles
                    ->map(fn (JobRole $role): array => $this->summarizeByName($role))
                    ->values()
                    ->all(),
            ),
            'business_function_ids' => $this->when($this->relationLoaded('businessFunctions'), fn (): array => $this->business_function_ids),
            'business_functions' => $this->when(
                $this->relationLoaded('businessFunctions'),
                fn (): array => $this->businessFunctions
                    ->sortBy('name')
                    ->map(static fn ($function): array => ['id' => $function->id, 'name' => $function->name])
                    ->values()
                    ->all(),
            ),
            'company_id' => $this->company_id,
            'primary_operational_site_id' => $this->primary_operational_site_id,
            'remote_operational_site_ids' => $this->remote_operational_site_ids,
            'product_lines' => $this->when(
                $this->relationLoaded('productLines'),
                fn (): array => $this->summarizeProductLines($this->productLines),
            ),

            'reports_to' => $this->when(
                $this->relationLoaded('reportsTo'),
                fn (): array => $this->reportsTo
                    ->map(fn (User $manager): array => $this->reference($manager, static fn (User $user): string => $user->name))
                    ->values()
                    ->all(),
            ),
            'company' => $this->when(
                $this->relationLoaded('company') && $this->company !== null,
                fn (): array => $this->reference($this->company, static fn (Company $company): string => $company->denomination, static fn (Company $company): ?string => $company->vat_number),
            ),
            'primary_operational_site' => $this->when(
                $this->relationLoaded('operationalSites') && $this->primarySite() !== null,
                fn (): array => $this->reference($this->primarySite(), $this->operationalSiteLabel(...), $this->operationalSiteSubtitle(...)),
            ),
            'remote_operational_sites' => $this->when(
                $this->relationLoaded('operationalSites'),
                fn (): array => $this->remoteSites()
                    ->map(fn (OperationalSite $site): array => $this->reference($site, $this->operationalSiteLabel(...), $this->operationalSiteSubtitle(...)))
                    ->values()
                    ->all(),
            ),
        ];
    }

    /**
     * IBAN and holder (spec 0272 D-5/D-6), and the hourly cost the salary in
     * force today amounts to (spec 0277 D-5, with the history loaded): the
     * keys exist ONLY for an actor holding `users.manageCompensation`, so a
     * viewer without it cannot tell whether the data is set.
     *
     * @return array<string, mixed>
     */
    private function paymentData(Request $request): array
    {
        if (! $request->user()?->can(UserPolicy::MANAGE_COMPENSATION_PERMISSION)) {
            return [];
        }

        return [
            'iban' => $this->iban,
            'iban_holder' => $this->iban_holder,
            ...($this->relationLoaded('contracts') ? ['hourly_cost' => $this->currentHourlyCost()] : []),
        ];
    }

    private function currentHourlyCost(): ?float
    {
        $cost = $this->contractInForceToday()?->hourlyCost();

        return $cost === null ? null : (float) $cost;
    }

    /**
     * The condition in force today (spec 0272 D-2): its start and CCNL level.
     *
     * @return array{starts_on: string, collective_agreement_level: array{id: int, label: string}|null}|null
     */
    private function currentContract(): ?array
    {
        $contract = $this->contractInForceToday();

        if ($contract === null) {
            return null;
        }

        $level = $contract->collectiveAgreementLevel;

        return [
            'starts_on' => $contract->starts_on->toDateString(),
            'collective_agreement_level' => $level === null ? null : [
                'id' => $level->id,
                'label' => "{$level->agreement->name} – {$level->code}",
            ],
        ];
    }

    /** The latest `starts_on` not after today, read off the eager-loaded history (no extra query). */
    private function contractInForceToday(): ?EmploymentContract
    {
        $today = today();

        return $this->contracts
            ->filter(fn (EmploymentContract $contract): bool => $contract->starts_on->lte($today))
            ->sortByDesc('starts_on')
            ->first();
    }

    /**
     * The competence rows in the shape shared by every owner of the
     * collection (spec 0111): `{id, business_function, product_category}`,
     * each reference `{id, name}` — deliberately NOT the `{id, label}`
     * reference() shape used by this resource's other relations, so the
     * frontend reads a user's rows exactly as it reads an opportunity's.
     *
     * @param  iterable<int, Model>  $lines
     * @return array<int, array{id: int, business_function: array{id: int, name: string}|null, product_category: array{id: int, name: string}|null}>
     */
    private function summarizeProductLines(iterable $lines): array
    {
        return collect($lines)
            ->map(fn (Model $line): array => [
                'id' => $line->id,
                'business_function' => $this->summarizeByName($line->businessFunction),
                'product_category' => $this->summarizeByName($line->productCategory),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function summarizeByName(?Model $related): ?array
    {
        return $related === null ? null : ['id' => $related->id, 'name' => $related->name];
    }

    /**
     * The at-most-one PHYSICAL site out of the eager-loaded `operationalSites`
     * collection (D-3) — reads the already-loaded relation, same no-N+1
     * reasoning as EmploymentProfile::primaryOperationalSiteId().
     */
    private function primarySite(): ?OperationalSite
    {
        return $this->operationalSites->first(fn (OperationalSite $site): bool => (bool) $site->pivot->is_primary);
    }

    /**
     * The zero-or-more REMOTE sites out of the same eager-loaded collection.
     *
     * @return Collection<int, OperationalSite>
     */
    private function remoteSites(): Collection
    {
        return $this->operationalSites->reject(fn (OperationalSite $site): bool => (bool) $site->pivot->is_primary);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  TModel  $model
     * @param  callable(TModel): string  $label
     * @param  (callable(TModel): ?string)|null  $subtitle
     * @return array{id: int, label: string, subtitle?: string|null}
     */
    private function reference(mixed $model, callable $label, ?callable $subtitle = null): array
    {
        $reference = ['id' => $model->id, 'label' => $label($model)];

        if ($subtitle !== null) {
            $reference['subtitle'] = $subtitle($model);
        }

        return $reference;
    }

    /**
     * "line1 - city" when a city is known, else just "line1" (spec 0015,
     * mirroring the operational-sites for-select label).
     */
    private function operationalSiteLabel(OperationalSite $site): string
    {
        $address = $site->primaryAddress;
        $city = $address?->city?->localizedName();

        return $city !== null ? "{$address->line1} - {$city}" : (string) $address?->line1;
    }

    private function operationalSiteSubtitle(OperationalSite $site): ?string
    {
        return $site->primaryAddress?->postal_code;
    }
}
