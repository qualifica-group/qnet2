<?php

declare(strict_types=1);

namespace App\Http\Requests\RequestManagement;

use App\DataObjects\RequestManagement\CreateRequestData;
use App\DataObjects\Users\ProfileData;
use App\Http\Requests\Concerns\ValidatesProductLines;
use App\Http\Requests\Concerns\ValidatesRequestClientProfile;
use App\Http\Requests\Concerns\ValidatesRewards;
use App\Services\Opportunities\ProductCategoryCoherence;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /api/request-management (spec 0057): creates the Opportunity behind
 * a "Gestione Richieste" row (the record IS an Opportunity, D-1). The client
 * anagraphic block is EXACTLY one of two mutually-exclusive branches (D-2):
 * `registry_id` (an existing Registry, untouched) or `client_identity` (+
 * optional `client_contacts`/`client_address`), which creates a brand-new
 * Registry+PersonalData. `product_lines` reuses ValidatesProductLines
 * VERBATIM (D-3, same rules as the opportunities form).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller, mirroring every other action of this module —
 * `request-management.create`). This request deliberately does NOT compose
 * EnforcesFieldPermissions: creation is gated WHOLESALE by
 * `request-management.create`, which is what authorizes setting a request's
 * INITIAL attribution — `source_id` (Fonte), `reporter_id` (Segnalatore) and
 * the `rewards` block (buono, beneficiary = reporter). The one exception is
 * `operator_id` (GA2), a supervisory act that needs
 * `request-management.assignOperator` ON TOP: the controller rejects a
 * non-null value from an actor without it. The per-field readonly
 * matrix (RequestManagementAuthorization::fields()) governs who may LATER edit
 * those fields on an existing record through the work panel's PATCH, a
 * distinct lifecycle concern; enforcing it here would need a persisted model
 * that does not exist yet at create time.
 */
class StoreRequestRequest extends FormRequest
{
    use ValidatesProductLines;
    use ValidatesRequestClientProfile;
    use ValidatesRewards;

    public function authorize(): bool
    {
        // Authorization handled in the controller (request-management.create).
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        // D-2's XOR resolved once here (not via requiredIf/prohibitedIf
        // closures composed with `sometimes`): `sometimes` skips a rule
        // entirely for an ABSENT key, which would silently let
        // registry_id's own requiredIf never fire when neither branch is
        // submitted — `required`/`prohibited` are implicit rules that always
        // evaluate presence, so the plain conditional below is both simpler
        // and correct.
        $hasIdentity = $this->filled('client_identity');

        $rules = array_merge(
            [
                // Both halves of the XOR live on this single field, so
                // either branch's violation reports on it (AC-004/AC-005).
                'registry_id' => [
                    $hasIdentity ? 'prohibited' : 'required',
                    'nullable', 'integer', Rule::exists('registries', 'id'),
                ],
                // Initial attribution, independent of the anagrafica XOR.
                // Fonte is MANDATORY (user directive 2026-07-29): a request
                // always knows where it came from. Segnalatore stays optional
                // and nullable — absent/null leaves that slot empty, the same
                // semantics the opportunities create payload carries.
                'source_id' => ['required', 'integer', Rule::exists('sources', 'id')],
                'reporter_id' => ['sometimes', 'nullable', 'integer', Rule::exists('referents', 'id')],
                // The GA2 "Operatore" (user directive 2026-07-29). Optional
                // here; submitting a non-null value additionally requires
                // `request-management.assignOperator`, enforced by the
                // controller (this class holds no authorization, see above).
                'operator_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')],
                // "Prodotti di interesse" (user directive 2026-07-31): the
                // same picker the work panel carries, available already at
                // creation. OPTIONAL here — the operator often records them
                // only after the first call — but whatever is picked must be
                // coherent with `product_lines` (withValidator below).
                'products_of_interest' => ['sometimes', 'array'],
                'products_of_interest.*' => ['integer', Rule::exists('products', 'id')],
                // Sede operativa (spec 0056, user directive 2026-07-31): the
                // field that scopes the operator list, so the create form
                // carries it exactly like the work panel. Plain optional FK —
                // no extra ability on top (the per-field matrix governs the
                // LATER edit through PATCH, see the class doc).
                'operational_site_id' => ['sometimes', 'nullable', 'integer', Rule::exists('operational_sites', 'id')],
                // The operative fields the work panel already edits, made
                // available at creation too (user directive 2026-07-31: "la
                // create il piu' simile possibile al pannello"). All OPTIONAL —
                // a request can still be opened knowing nothing but its
                // anagrafica and its product lines. Spec 0083, D-2: the
                // working-status pair (the former workflow-status override
                // field and its accompanying `note`) is GONE — the
                // Opportunity resolves no working state of its own any more.
                'next_callback_at' => ['sometimes', 'nullable', 'date'],
                'general_notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
                // "Informazioni aggiuntive" (user directive 2026-08-07):
                // shallow here on purpose. The per-code applicability/type/
                // required validation runs in RequestCreationService against
                // the applicable set the INSERTED product lines produce — the
                // only place that can resolve it (it does not exist before the
                // insert), and the same single place the PATCH channel uses.
                'attribute_values' => ['sometimes', 'array'],
            ],
            $this->clientProfileRules(),
            $this->productLinesRules(required: true),
            // Spec 0059: reward assignments for the reporter. Same shape/rules
            // as the opportunities create payload; the D-3 "rewards need a
            // reporter" invariant runs in withValidator() against THIS request.
            $this->rewardsRules(),
        );

        // D-2: "i blocchi client_* sono rifiutati" when an existing registry
        // is chosen — appended (not replacing) clientProfileRules()' own base
        // shape rules for the two.
        $rules['client_contacts'][] = Rule::prohibitedIf(fn (): bool => $this->filled('registry_id'));
        $rules['client_address'][] = Rule::prohibitedIf(fn (): bool => $this->filled('registry_id'));

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateClientProfile($validator);
            $this->validateProductLines($validator);
            // null opportunity: on create there is nothing persisted, so only
            // the "non-empty rewards require a submitted reporter_id" half of
            // the D-3 guard can fire here (the "cannot clear a reporter that
            // still has rewards" half needs an existing record).
            $this->validateRewards($validator, null);
            $this->validateProductCategoryCoherence($validator);
        });
    }

    /**
     * The coherence rule (user directive 2026-07-31): a product of interest
     * picked at creation must belong to one of the submitted product-line
     * categories. Both collections travel in THIS payload — nothing is
     * persisted yet — so the check belongs here, unlike on the PATCH, where
     * the same rule (ProductCategoryCoherence, shared) needs the
     * record's stored sets.
     *
     * Skipped when `product_lines` is malformed: its own rules already report
     * that, and a partial category set would produce a second, misleading
     * error on the picker.
     */
    private function validateProductCategoryCoherence(Validator $validator): void
    {
        $products = $this->input('products_of_interest');
        $lines = $this->input('product_lines');

        // A malformed `product_lines` is already reported by its own rules; a
        // partial category set would add a second, misleading error here.
        $linesRejected = collect($validator->errors()->keys())
            ->contains(static fn (string $key): bool => str_starts_with($key, 'product_lines'));

        if (! is_array($products) || $products === [] || $linesRejected) {
            return;
        }

        $categoryIds = collect(is_array($lines) ? $lines : [])
            ->filter(static fn (mixed $line): bool => is_array($line) && isset($line['product_category_id']))
            ->map(static fn (array $line): int => (int) $line['product_category_id'])
            ->all();

        $coherence = app(ProductCategoryCoherence::class);
        $offending = $coherence->offendingProducts(array_map(intval(...), $products), $categoryIds);

        if ($offending !== []) {
            $validator->errors()->add(
                'products_of_interest',
                $coherence->message($offending, ProductCategoryCoherence::REQUEST_MESSAGE),
            );
        }
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service).
     */
    public function toData(): CreateRequestData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return new CreateRequestData(
            registryId: isset($validated['registry_id']) ? (int) $validated['registry_id'] : null,
            clientProfile: $this->buildClientProfile(),
            productLines: self::normalizeProductLines((array) $validated['product_lines']),
            sourceId: isset($validated['source_id']) ? (int) $validated['source_id'] : null,
            reporterId: isset($validated['reporter_id']) ? (int) $validated['reporter_id'] : null,
            productsOfInterest: array_key_exists('products_of_interest', $validated)
                ? array_values(array_unique(array_map(intval(...), (array) $validated['products_of_interest'])))
                : null,
            rewards: array_key_exists('rewards', $validated) ? self::normalizeRewardTypeIds((array) $validated['rewards']) : null,
            operatorId: isset($validated['operator_id']) ? (int) $validated['operator_id'] : null,
            operationalSiteId: isset($validated['operational_site_id']) ? (int) $validated['operational_site_id'] : null,
            nextCallbackAt: $validated['next_callback_at'] ?? null,
            generalNotes: $validated['general_notes'] ?? null,
            attributeValues: array_key_exists('attribute_values', $validated)
                ? (array) $validated['attribute_values']
                : null,
        );
    }

    /**
     * D-2: null on the `registry_id` branch (nothing to write); assembled
     * from the trait's typed payload otherwise. `client_address` is a SINGLE
     * row (this panel's own narrower shape, ValidatesRequestClientProfile),
     * wrapped into ProfileData's array-of-addresses convention.
     */
    private function buildClientProfile(): ?ProfileData
    {
        $payload = $this->clientProfilePayload();

        if (! isset($payload['client_identity'])) {
            return null;
        }

        return new ProfileData(
            card: $payload['client_identity'],
            contacts: $payload['client_contacts'] ?? null,
            addresses: isset($payload['client_address']) ? [$payload['client_address']] : null,
        );
    }

    /**
     * The submitted `{reward_type_id}` rows reduced to a deduplicated id list
     * — the shape CreateOpportunityData/RewardAssignmentWriter consume (the
     * beneficiary is derived from the Opportunity's reporter, never here).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, int>
     */
    private static function normalizeRewardTypeIds(array $rows): array
    {
        return array_values(array_unique(array_map(
            static fn (array $row): int => (int) $row['reward_type_id'],
            $rows,
        )));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{business_function_id: int, product_category_id: int}>
     */
    private static function normalizeProductLines(array $rows): array
    {
        return array_map(
            static fn (array $row): array => [
                'business_function_id' => (int) $row['business_function_id'],
                'product_category_id' => (int) $row['product_category_id'],
            ],
            $rows,
        );
    }
}
