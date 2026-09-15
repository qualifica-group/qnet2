<?php

namespace App\Http\Requests\Concerns;

use App\DataObjects\Users\EmploymentData;
use App\Enums\QualificationTypeEnum;
use App\Enums\RelationshipTypeEnum;
use App\Services\Assignment\CompetenceLineSetValidator;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
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
 * `product_lines` (spec 0111, the assignment competence) behaves identically,
 * on its own child table — and, since D-1 dropped the single
 * `business_function_id` column, it is also the only place a user's business
 * function is written. Spec 0129 adds a THIRD, plain scalar way to widen that
 * competence: `covers_all_product_categories` follows the same semantics as
 * `is_manager` (absent on write = false, D-10), and — unlike the tri-state
 * fields above — a true value forces `product_lines` empty server-side
 * (EmploymentWriter, D-2), never trusting the payload's own emptiness.
 *
 * @phpstan-require-extends FormRequest
 */
trait ValidatesEmployment
{
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

            'employment.is_manager' => ['sometimes', 'boolean'],
            // Spec 0129 D-1: the wildcard flag, a plain scalar like is_manager.
            'employment.covers_all_product_categories' => ['sometimes', 'boolean'],
            'employment.job_description' => ['nullable', 'string', 'max:255'],
            'employment.reports_to_id' => array_filter([
                'nullable',
                'integer',
                Rule::exists('users', 'id'),
                // No self-reference: only meaningful on update, where the
                // target user id is known (AC-006); never applies on create.
                $currentUserId !== null ? Rule::notIn([$currentUserId]) : null,
            ]),
            'employment.relationship_type' => ['nullable', Rule::enum(RelationshipTypeEnum::class)],

            'employment.company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')],

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
            ...$this->competenceLineSetValidator()->rules('employment.product_lines'),

            'employment.qualification_type' => ['nullable', Rule::enum(QualificationTypeEnum::class)],
            'employment.hired_at' => ['nullable', 'date'],
            'employment.terminated_at' => ['nullable', 'date', 'after_or_equal:employment.hired_at'],

            'employment.standard_daily_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'employment.break_daily_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
        ];
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
            isManager: (bool) $this->input('employment.is_manager', false),
            coversAllProductCategories: $this->boolean('employment.covers_all_product_categories'),
            jobDescription: $this->input('employment.job_description'),
            reportsToId: $this->nullableInt('employment.reports_to_id'),
            relationshipType: RelationshipTypeEnum::tryFrom((string) $this->input('employment.relationship_type')),
            companyId: $this->nullableInt('employment.company_id'),
            primaryOperationalSiteIdProvided: $this->has('employment.primary_operational_site_id'),
            primaryOperationalSiteId: $this->nullableInt('employment.primary_operational_site_id'),
            remoteOperationalSiteIdsProvided: $this->has('employment.remote_operational_site_ids'),
            remoteOperationalSiteIds: $this->submittedIds('employment.remote_operational_site_ids'),
            productLinesProvided: $this->has('employment.product_lines'),
            productLines: $this->submittedProductLines(),
            qualificationType: QualificationTypeEnum::tryFrom((string) $this->input('employment.qualification_type')),
            hiredAt: $this->input('employment.hired_at'),
            terminatedAt: $this->input('employment.terminated_at'),
            standardDailyMinutes: $this->nullableInt('employment.standard_daily_minutes'),
            breakDailyMinutes: $this->nullableInt('employment.break_daily_minutes'),
        );
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
