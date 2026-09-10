<?php

declare(strict_types=1);

namespace App\Http\Requests\RequestManagement;

use App\Enums\LeadAssignmentMode;
use App\Models\Quote;
use App\Models\User;
use App\Services\Assignment\AssignmentCandidates;
use App\Services\Assignment\AssignmentSiteResolver;
use App\Services\Assignment\QuoteCompetence;
use App\Services\RequestManagement\RequestManagementScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates POST /api/request-management/assign-operators (user directive
 * 2026-07-23, "come nei lead"): bulk-assign the GA2 "Operatore" of many
 * requests at once, either to a single chosen operator (`mode=single`) or
 * load-balanced across the operators of each offer's own Sede
 * (`mode=balanced`).
 *
 * `operational_site_id` is `prohibited` since spec 0113 (AC-021): an offer's
 * Sede IS `quotes.operational_site_id` (D-4), so the action reads it instead
 * of writing it and it is no longer the caller's to choose. Rejecting the key
 * outright — rather than ignoring it — keeps a stale client from believing it
 * still steers the assignment.
 *
 * Since the user directive 2026-09-10 the two modes are also symmetric on the
 * SERVER, for this module alone: `single` no longer accepts any operator the
 * UI happened to offer, it accepts only one the offers themselves would have
 * accepted (validateOperatorCoversEveryRequest). This reverses spec 0113's
 * R-1 for Gestione richieste; the import wizard and the Lead table keep the
 * UI-only filter.
 *
 * LeadAssignmentMode is REUSED as-is rather than duplicated: it is the very
 * same two-mode contract the shared AssignOperatorsDialog submits (renaming it
 * would touch the leads/imports call sites, out of this scope).
 *
 * `request_ids` are Offerta (Quote) ids (spec 0086, D-2).
 *
 * Authorization is intentionally NOT handled here (it stays in the controller:
 * the `request-management.update` gate plus the per-row D-3 scope), same
 * convention as UpdateRequestRequest.
 */
class AssignRequestOperatorsRequest extends FormRequest
{
    /**
     * The two abilities RequestManagementController::assignOperators() aborts
     * on. Mirrored here for ORDERING alone, never as the gate itself: a
     * FormRequest is validated BEFORE the controller body runs, so without
     * this an actor who may not assign would be answered with a 422 naming
     * offers instead of the 403 that is theirs — a regression on the gate and
     * a leak of which offers exist to someone who may not act on them.
     *
     * It therefore reads like a redundant duplicate of the controller's gate
     * and is not one: DELETING IT to "simplify" silently turns that 403 into
     * an informative 422. It may only go away if the check itself moves
     * downstream of the gate.
     *
     * @var array<int, string>
     */
    private const array ASSIGNMENT_ABILITIES = [
        'request-management.update',
        'request-management.assignOperator',
    ];

    public function authorize(): bool
    {
        // Authorization handled in the controller (permission + D-3 scope).
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'request_ids' => ['required', 'array', 'min:1'],
            'request_ids.*' => ['integer', Rule::exists('quotes', 'id')],
            'operational_site_id' => ['prohibited'],
            'mode' => ['required', Rule::enum(LeadAssignmentMode::class)],
            'operator_id' => ['required_if:mode,single', 'integer', Rule::exists('users', 'id')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateOperatorCoversEveryRequest($validator);
        });
    }

    /**
     * `mode=single`: the chosen operator must be a CANDIDATE of every offer
     * the actor actually reaches (user directive 2026-09-10). What counts as
     * a candidate is not re-implemented here — it is the very composition
     * `mode=balanced` distributes over: the operators of the offer's own
     * `quotes.operational_site_id` (spec 0113, D-4) narrowed by competence
     * for its own product lines (spec 0110, INV-1).
     *
     * The two halves stay asymmetric, as everywhere else: an offer demanding
     * no category is unconstrained on the COMPETENCE half (INV-4a), never on
     * the Sede half. An offer with NO Sede therefore has an empty pool
     * (AC-007) and no operator at all can be valid for it — it is reported as
     * offending rather than waved through, which would be the one way to
     * write an operator this endpoint could never have distributed.
     *
     * ALL-OR-NOTHING, following BulkAssignRequest::validateProductIdsCoverage():
     * a single offending offer rejects the whole batch and nothing is written,
     * instead of assigning half a selection the operator made in one gesture.
     * The message names the offending offer ids — the very keys the client
     * submitted — and no class or model name.
     */
    private function validateOperatorCoversEveryRequest(Validator $validator): void
    {
        if ($this->input('mode') !== LeadAssignmentMode::Single->value || $validator->errors()->isNotEmpty()) {
            return;
        }

        $user = $this->user();

        if (! $user instanceof User || ! $this->mayAssign($user)) {
            return;
        }

        $requestIds = $this->reachableRequestIds($user);

        if ($requestIds === []) {
            return;
        }

        $offendingIds = $this->offendingRequestIds($requestIds, (int) $this->input('operator_id'));

        if ($offendingIds !== []) {
            $validator->errors()->add(
                'operator_id',
                'The chosen operator is not enabled for the Sede or the product categories of requests '
                    .implode(', ', $offendingIds).': nothing was assigned.',
            );
        }
    }

    private function mayAssign(User $user): bool
    {
        foreach (self::ASSIGNMENT_ABILITIES as $ability) {
            if (! $user->can($ability)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The submitted offers the actor may actually write, through the SAME
     * RequestManagementScope predicate RequestAssignmentService applies before
     * writing (D-3). An out-of-scope id must not reach the check at all: the
     * action skips it in silence, so letting one fail the batch would both
     * contradict that skip and tell the actor an offer they cannot see exists.
     *
     * @return array<int, int>
     */
    private function reachableRequestIds(User $user): array
    {
        $requestIds = self::normalizedIds($this->input('request_ids', []));

        if ($requestIds === []) {
            return [];
        }

        return RequestManagementScope::scopeToActor(Quote::query()->whereIn('id', $requestIds), $user)
            ->orderBy('id')
            ->pluck('id')
            ->map(intval(...))
            ->all();
    }

    /**
     * The offers $operatorId is no candidate of, ascending.
     *
     * @param  array<int, int>  $requestIds  ordered ascending
     * @return array<int, int>
     */
    private function offendingRequestIds(array $requestIds, int $operatorId): array
    {
        $candidatesByRequest = app(AssignmentCandidates::class)->byRecord(
            app(AssignmentSiteResolver::class)->forQuotes($requestIds),
            app(QuoteCompetence::class)->requiredByQuote($requestIds),
        );

        return array_values(array_filter(
            $requestIds,
            static fn (int $requestId): bool => ! in_array($operatorId, $candidatesByRequest[$requestId] ?? [], true),
        ));
    }

    /**
     * The submitted request ids, deduplicated.
     *
     * @return array<int, int>
     */
    public function requestIds(): array
    {
        return self::normalizedIds($this->validated('request_ids', []));
    }

    /**
     * @return array<int, int>
     */
    private static function normalizedIds(mixed $ids): array
    {
        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_unique(array_map(intval(...), $ids)));
    }

    public function mode(): LeadAssignmentMode
    {
        return LeadAssignmentMode::from((string) $this->validated('mode'));
    }

    public function operatorId(): ?int
    {
        $value = $this->validated('operator_id');

        return $value === null ? null : (int) $value;
    }
}
