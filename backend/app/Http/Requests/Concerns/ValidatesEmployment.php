<?php

namespace App\Http\Requests\Concerns;

use App\DataObjects\Users\EmploymentData;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Rules\ValidIban;
use App\Services\Assignment\CompetenceLineSetValidator;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * Shared validation + DTO assembly for the optional nested `employment` object
 * accepted by the user write endpoints (spec 0015). Used verbatim by both
 * StoreUserRequest and UpdateUserRequest so the nested rules live in one place.
 *
 * Wire semantics: `employment` absent leaves the row untouched; a present
 * object upserts it; an explicit `employment: null` deletes it (update only —
 * on create it is equivalent to "absent", see EmploymentWriter).
 *
 * The two site-membership fields (spec 0103) carry a second, per-field
 * tri-state on top of the above: `primary_operational_site_id`/
 * `remote_operational_site_ids` absent from the payload leaves that side of
 * the membership untouched; present with null/empty clears it. See
 * EmploymentData's docblock and EmploymentWriter::syncSiteMemberships().
 * `reports_to_ids` (spec 0166 D-2, replacing the single `reports_to_id`)
 * behaves identically, on its own pivot (`employment_profile_manager`).
 * `product_lines` (spec 0111, the assignment competence) behaves identically,
 * on its own child table — and, since D-1 dropped the single
 * `business_function_id` column, it is also the only place a user's business
 * function is written. Spec 0129 adds a THIRD, plain scalar way to widen that
 * competence: `covers_all_product_categories` (absent on write = false, D-10) —
 * unlike the tri-state fields above, a true value forces `product_lines` empty server-side
 * (EmploymentWriter, D-2), never trusting the payload's own emptiness.
 *
 * @phpstan-require-extends FormRequest
 */
trait ValidatesEmployment
{
    /** Columns that mirror the current contract condition (written by the contract service only). */
    private const array READ_ONLY_EMPLOYMENT_FIELDS = ['relationship_type', 'company_id'];

    /**
     * Validation rules for the nested `employment.*` object. Merged into each
     * request's own account-field rules; they never change the account rules.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function employmentRules(): array
    {
        $currentUserId = $this->route('user')?->id;
        // Read once so both the notIn() guard below and toEmployment() see
        // the same value (AC-006: a remote id equal to the primary is a 422,
        // not a silent drop).
        $primaryOperationalSiteId = $this->nullableInt('employment.primary_operational_site_id');

        return [
            'employment' => ['sometimes', 'nullable', 'array'],

            // Spec 0129 D-1: the wildcard flag, a plain scalar.
            'employment.covers_all_product_categories' => ['sometimes', 'boolean'],
            // Spec 0194 D-3: absent = untouched, so no default here.
            'employment.is_assignable' => ['sometimes', 'boolean'],
            // Spec 0271 D-2: job roles from the catalogue, same tri-state as
            // `reports_to_ids` (absent = untouched, null/[] = cleared).
            'employment.job_role_ids' => ['sometimes', 'nullable', 'array'],
            'employment.job_role_ids.*' => ['integer', 'distinct', Rule::exists('job_roles', 'id')],
            // Spec 0275 D-3: business function membership, same tri-state.
            'employment.business_function_ids' => ['sometimes', 'nullable', 'array'],
            'employment.business_function_ids.*' => ['integer', 'distinct', Rule::exists('business_functions', 'id')],
            // Spec 0276 D-5: the functions this person manages, same tri-state
            // as the membership (absent/null = untouched, [] = none managed).
            'employment.managed_business_function_ids' => ['sometimes', 'nullable', 'array'],
            'employment.managed_business_function_ids.*' => ['integer', 'distinct', Rule::exists('business_functions', 'id')],
            // Spec 0166 (D-2/D-8): a user may report to several managers, so
            // the single `reports_to_id` becomes a tri-state array, same shape
            // as `remote_operational_site_ids` below.
            'employment.reports_to_ids' => ['sometimes', 'nullable', 'array'],
            'employment.reports_to_ids.*' => array_filter([
                'integer',
                'distinct',
                Rule::exists('users', 'id'),
                // No self-reference: only meaningful on update, where the
                // target user id is known (AC-006); never applies on create.
                $currentUserId !== null ? Rule::notIn([$currentUserId]) : null,
            ]),
            // Site membership (spec 0103 D-9, replacing the single
            // `employment.operational_site_id`): at most one physical site
            // plus any number of remote ones.
            'employment.primary_operational_site_id' => ['nullable', 'integer', Rule::exists('operational_sites', 'id')],
            'employment.remote_operational_site_ids' => ['nullable', 'array'],
            'employment.remote_operational_site_ids.*' => array_filter([
                'integer',
                'distinct',
                Rule::exists('operational_sites', 'id'),
                // AC-006: a remote id cannot double as the primary one.
                $primaryOperationalSiteId !== null ? Rule::notIn([$primaryOperationalSiteId]) : null,
            ]),

            // Assignment competence (spec 0111): same per-field tri-state as
            // the site membership above — absent leaves the rows untouched,
            // an empty array clears them. Deliberately NO `min:1`, unlike the
            // offer/project/campaign collections (D-8): a user's competence is
            // optional, so emptying it is a legitimate write. Spec 0129 D-6/D-9:
            // these rows have their OWN rule set (CompetenceLineSetValidator),
            // not ProductLineSetValidator's — no selectability constraint, a
            // nullable category.
            'employment.product_lines' => ['sometimes', 'nullable', 'array'],
            ...$this->competenceLineSetValidator()->rules('employment.product_lines', $this->exemptCompetenceCategoryIds()),

            'employment.hired_at' => ['nullable', 'date'],
            'employment.terminated_at' => ['nullable', 'date', 'after_or_equal:employment.hired_at'],

            'employment.standard_daily_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'employment.break_daily_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],

            // Payment data (spec 0272 D-6): absent = untouched, null = cleared.
            'employment.iban' => ['sometimes', 'nullable', 'string', 'max:64', $this->ibanRule()],
            'employment.iban_holder' => ['sometimes', 'nullable', 'string', 'max:191'],
        ];
    }

    /**
     * Spec 0272 D-2 / AC-009: `relationship_type` and `company_id` are the
     * read-only copy of the current contract condition, so a payload carrying
     * them (an older client) is IGNORED rather than rejected: they are dropped
     * before validation, which also keeps them out of the field-permission diff.
     */
    protected function discardReadOnlyEmploymentInput(): void
    {
        $input = $this->all();

        Arr::forget($input, array_map(
            static fn (string $field): string => "employment.{$field}",
            self::READ_ONLY_EMPLOYMENT_FIELDS,
        ));

        $this->replace($input);
    }

    /**
     * Refuses, with a 403, any attempt to write the payment data without
     * `users.manageCompensation` (spec 0272 D-5). Called first from each host
     * request's withValidator(), so the refusal wins over every value-level
     * error and the submitted IBAN is never echoed back in a message.
     *
     * @throws AuthorizationException
     */
    protected function assertMayWriteCompensation(): void
    {
        $touchesPayment = $this->has('employment.iban') || $this->has('employment.iban_holder');

        if ($touchesPayment && ! $this->user()?->can(UserPolicy::MANAGE_COMPENSATION_PERMISSION)) {
            throw new AuthorizationException;
        }
    }

    /**
     * Formal IBAN check on the normalized value (the same one that gets
     * stored); the message never carries the submitted value.
     */
    private function ibanRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && trim($value) !== '' && ! ValidIban::isValid(ValidIban::normalize($value))) {
                $fail(__('The IBAN is not valid.'));
            }
        };
    }

    /**
     * The competence's cross-row rules, called by each host request from its
     * OWN withValidator() — same composition as ValidatesProductLines::
     * validateProductLines() on the opportunity requests.
     *
     * Plus the flag/rows exclusivity of spec 0129 D-2: `covers_all_product_
     * categories: true` together with a non-empty `product_lines` is a 422 on
     * the collection itself, not on a row — checked here rather than as a
     * plain rule because it spans two sibling fields.
     */
    protected function validateEmploymentProductLines(Validator $validator): void
    {
        $lines = $this->input('employment.product_lines');

        if ($this->boolean('employment.covers_all_product_categories') && is_array($lines) && $lines !== []) {
            $validator->errors()->add(
                'employment.product_lines',
                __(CompetenceLineSetValidator::ALL_CATEGORIES_WITH_LINES_MESSAGE),
            );

            return;
        }

        if (! is_array($lines)) {
            return;
        }

        foreach ($this->competenceLineSetValidator()->crossRowErrors($lines, 'employment.product_lines') as $key => $message) {
            $validator->errors()->add($key, $message);
        }
    }

    /**
     * The categories already persisted on the competence of the user being
     * updated (spec 0208 D-2). Empty on create (no route model).
     *
     * @return array<int, int>
     */
    private function exemptCompetenceCategoryIds(): array
    {
        $user = $this->route('user');

        if (! $user instanceof User) {
            return [];
        }

        return $user->employment?->productLines()
            ->whereNotNull('product_category_id')
            ->pluck('product_category_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all() ?? [];
    }

    private function competenceLineSetValidator(): CompetenceLineSetValidator
    {
        return app(CompetenceLineSetValidator::class);
    }

    /**
     * Build the typed EmploymentData DTO (or its delete sentinel) from the
     * submitted nested payload, or null when `employment` is absent (leave
     * the row untouched).
     */
    public function toEmployment(): ?EmploymentData
    {
        if (! $this->has('employment')) {
            return null;
        }

        if ($this->input('employment') === null) {
            return EmploymentData::delete();
        }

        return new EmploymentData(
            coversAllProductCategories: $this->boolean('employment.covers_all_product_categories'),
            isAssignable: $this->has('employment.is_assignable') ? $this->boolean('employment.is_assignable') : null,
            reportsToIdsProvided: $this->has('employment.reports_to_ids'),
            reportsToIds: $this->submittedIds('employment.reports_to_ids'),
            primaryOperationalSiteIdProvided: $this->has('employment.primary_operational_site_id'),
            primaryOperationalSiteId: $this->nullableInt('employment.primary_operational_site_id'),
            remoteOperationalSiteIdsProvided: $this->has('employment.remote_operational_site_ids'),
            remoteOperationalSiteIds: $this->submittedIds('employment.remote_operational_site_ids'),
            productLinesProvided: $this->has('employment.product_lines'),
            productLines: $this->submittedProductLines(),
            jobRoleIdsProvided: $this->has('employment.job_role_ids'),
            jobRoleIds: $this->submittedIds('employment.job_role_ids'),
            // null reads as absent (spec 0275 contract), unlike the sibling pivots.
            businessFunctionIdsProvided: $this->input('employment.business_function_ids') !== null,
            businessFunctionIds: $this->submittedIds('employment.business_function_ids'),
            managedBusinessFunctionIdsProvided: $this->input('employment.managed_business_function_ids') !== null,
            managedBusinessFunctionIds: $this->submittedIds('employment.managed_business_function_ids'),
            hiredAt: $this->input('employment.hired_at'),
            terminatedAt: $this->input('employment.terminated_at'),
            standardDailyMinutes: $this->nullableInt('employment.standard_daily_minutes'),
            breakDailyMinutes: $this->nullableInt('employment.break_daily_minutes'),
            ibanProvided: $this->has('employment.iban'),
            iban: $this->submittedIban(),
            ibanHolderProvided: $this->has('employment.iban_holder'),
            ibanHolder: $this->filled('employment.iban_holder') ? (string) $this->input('employment.iban_holder') : null,
        );
    }

    /**
     * The submitted IBAN in its stored form (uppercase, no separators), or
     * null when blank.
     */
    private function submittedIban(): ?string
    {
        $value = $this->input('employment.iban');

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return ValidIban::normalize($value);
    }

    private function nullableInt(string $key): ?int
    {
        $value = $this->input($key);

        return $value === null || $value === '' ? null : (int) $value;
    }

    /**
     * The ids submitted under $key, cast to int, or an empty array when the
     * key is absent (EmploymentWriter reads the sibling *Provided flag to
     * tell "absent" from "explicitly emptied").
     *
     * @return array<int, int>
     */
    private function submittedIds(string $key): array
    {
        if (! $this->has($key)) {
            return [];
        }

        return collect((array) $this->input($key))
            ->filter(fn (mixed $id): bool => $id !== null && $id !== '')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * The submitted competence rows, normalized to the shape ProductLineWriter
     * inserts. Same "absent reads as empty" convention as submittedIds() above
     * — `productLinesProvided` is what tells the two apart. `product_category_id`
     * may be null (spec 0129 D-3, "every category of this row's function"), so
     * this checks key PRESENCE (`array_key_exists`), not `isset()`, which would
     * silently drop a null value.
     *
     * @return array<int, array{business_function_id: int, product_category_id: int|null}>
     */
    private function submittedProductLines(): array
    {
        return collect((array) $this->input('employment.product_lines'))
            ->filter(fn (mixed $line): bool => is_array($line)
                && array_key_exists('business_function_id', $line) && $line['business_function_id'] !== null
                && array_key_exists('product_category_id', $line))
            ->map(fn (array $line): array => [
                'business_function_id' => (int) $line['business_function_id'],
                'product_category_id' => $line['product_category_id'] === null ? null : (int) $line['product_category_id'],
            ])
            ->values()
            ->all();
    }
}
