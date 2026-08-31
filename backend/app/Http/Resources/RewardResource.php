<?php

namespace App\Http\Resources;

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Reward;
use App\Models\User;
use App\Services\Opportunities\OpportunityStatusResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * @mixin Reward
 *
 * Item shape for `GET /api/referents/{referent}/rewards` and
 * `PATCH /api/rewards/{reward}` (spec 0059/0060, the lazy detail endpoint and
 * the card's inline status edit). NO source-derived status value is ever
 * read from `rewards` itself (AC-016): `context` always projects the
 * origin's CURRENT relations. `source`/`context` are null when the origin
 * has been deleted (orphan `source_id`). `reward_status`, by contrast, IS a
 * value persisted on `rewards` itself (spec 0060 D-5) — the one exception to
 * "no status lives here".
 *
 * Two origins today (user directive 2026-08-31): `opportunity` and `quote`
 * — buildContext()/summarizeSource()/buildRelated() all dispatch on the
 * morph map ALIAS (`getMorphClass()`, never a FQCN — `morph_map_is_strict`),
 * one `match` arm each. `related` carries the OTHER of the two (an
 * Opportunita' holds at most one Offerta), so the card always shows both
 * references, whichever one the buono was born on.
 *
 * `context.workflow_status` is emitted ONLY by the Offerta arm: it is the
 * offer's own working-state row (spec 0083), and an Opportunita' has none of
 * its own — its status is COMPUTED from its quotes (`context.status`, spec
 * 0082), which both arms carry.
 *
 * Relies on the caller having eager-loaded `eagerLoad()`'s relations
 * (`rewardType`/`rewardStatus`/`source` with the Opportunity's own
 * `registry`/`productLines.productCategory`/`quotes.quoteWorkflowStatus`/
 * `managers.avatar` chain) so resolving any of this never N+1s (AC-017) —
 * the single source of truth shared by `ReferentRewardsController` and
 * `RewardController::updateStatus` so their eager-load specs can never drift
 * apart. Spec 0083, D-2: `context.workflow_status` is GONE — the Opportunity
 * carries no working-state row of its own any more.
 */
class RewardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $source = $this->source;

        return [
            'id' => $this->id,
            'assigned_at' => $this->assigned_at?->toDateString(),
            'notes' => $this->notes,
            'reward_type' => $this->summarizeRewardType($this->rewardType),
            'reward_status' => $this->summarizeRewardStatus($this->rewardStatus),
            'source' => $this->summarizeSource($source),
            'related' => $this->buildRelated($source),
            'context' => $this->buildContext($source),
        ];
    }

    /**
     * @return array<int|string, mixed>
     */
    public static function eagerLoad(): array
    {
        return [
            'rewardType',
            'rewardStatus',
            // morphWith: the `source` bag holds mixed origin types (today
            // only Opportunity), so its OWN relation chain must be declared
            // here to stay N+1-free (AC-017) — a plain nested eager-load
            // string can't reach across a MorphTo.
            'source' => static function (MorphTo $morphTo): void {
                $morphTo->morphWith([
                    Opportunity::class => [
                        'registry',
                        'productLines.productCategory',
                        // Spec 0082/0083: the computed status reads the
                        // quotes' own workflow statuses (falling back to the
                        // global default `open` row when there are none).
                        'quotes.quoteWorkflowStatus',
                        'managers.avatar',
                    ],
                    // The Offerta origin (2026-08-31 directive): its own
                    // context chain, plus the parent Opportunity the card
                    // shows as the cross-reference (`related`).
                    Quote::class => [
                        'opportunity.registry',
                        'opportunity.quotes.quoteWorkflowStatus',
                        'quoteWorkflowStatus',
                        'offerLines.product.category',
                        'supervisor.avatar',
                    ],
                ]);
            },
        ];
    }

    /**
     * @return array{id: int, name: string, color: string}|null
     */
    private function summarizeRewardType(?Model $rewardType): ?array
    {
        return $rewardType === null ? null : [
            'id' => $rewardType->id,
            'name' => $rewardType->name,
            'color' => $rewardType->color,
        ];
    }

    /**
     * @return array{id: int, name: string, color: string}|null
     */
    private function summarizeRewardStatus(?Model $rewardStatus): ?array
    {
        return $rewardStatus === null ? null : [
            'id' => $rewardStatus->id,
            'name' => $rewardStatus->name,
            'color' => $rewardStatus->color,
        ];
    }

    /**
     * @return array{type: string, id: int, name: string, path: string|null}|null
     */
    private function summarizeSource(?Model $source): ?array
    {
        if ($source === null) {
            return null;
        }

        $alias = $source->getMorphClass();

        return [
            'type' => $alias,
            'id' => $source->id,
            // An Offerta has no `name` column at all: its human identity is
            // the sequential `code` (QUO-0001), the same label every other
            // surface shows it under.
            'name' => match ($alias) {
                'quote' => (string) $source->code,
                default => (string) $source->name,
            },
            'path' => match ($alias) {
                'opportunity' => "/opportunities/{$source->id}",
                'quote' => "/quotes/{$source->id}",
                default => null,
            },
        ];
    }

    /**
     * The records linked to the origin that the card shows ALONGSIDE it
     * (user directive 2026-08-31: "il riferimento all'offerta e non solo
     * in opportunita'"). One Opportunita' carries at most one Offerta
     * (ValidatesSingleQuotePerOpportunity), so this is the counterpart of
     * whichever of the two the reward was born on — never the origin itself,
     * which `source` already carries.
     *
     * @return array<int, array{type: string, id: int, name: string, path: string|null}>
     */
    private function buildRelated(?Model $source): array
    {
        $counterparts = match ($source?->getMorphClass()) {
            'opportunity' => $source->quotes->all(),
            'quote' => array_filter([$source->opportunity]),
            default => [],
        };

        return array_values(array_filter(array_map(
            fn (Model $related): ?array => $this->summarizeSource($related),
            $counterparts,
        )));
    }

    /**
     * @return array{registry: array{id: int, name: string}|null, product_categories: array<int, array{id: int, name: string}>, status: array{source: string, distinct_count: int, entries: array<int, array{id: int, name: string, color: string|null, group: string, count: int}>}, operator: array{id: int, name: string, avatar_url: string|null}|null}|null
     */
    private function buildContext(?Model $source): ?array
    {
        if ($source === null) {
            return null;
        }

        return match ($source->getMorphClass()) {
            'opportunity' => $this->contextForOpportunity($source),
            'quote' => $this->contextForQuote($source),
            default => null,
        };
    }

    /**
     * The Offerta origin's live context. `status` stays the parent
     * Opportunita's COMPUTED commercial status — the same field, resolved
     * the same way, as an Opportunity-origin card, so the two never read as
     * different things; `workflow_status` adds what only an Offerta has, its
     * OWN working-state row (spec 0083). The categories come from the offer's
     * revenue lines (its own products), not from the Opportunita's product
     * lines, and the operator is the offer's Supervisore (spec 0086, D-3).
     *
     * @return array{registry: array{id: int, name: string}|null, product_categories: array<int, array{id: int, name: string}>, status: array<string, mixed>, workflow_status: array{id: int, name: string, color: string|null}|null, operator: array{id: int, name: string, avatar_url: string|null}|null}
     */
    private function contextForQuote(Quote $quote): array
    {
        $status = $quote->quoteWorkflowStatus;

        return [
            'registry' => $this->summarizeByName($quote->opportunity?->registry),
            'product_categories' => $this->summarizeCategories(
                collect($quote->offerLines)->map(static fn (Model $line): ?Model => $line->product?->category),
            ),
            'status' => $quote->opportunity === null
                ? ['source' => 'none', 'distinct_count' => 0, 'entries' => []]
                : app(OpportunityStatusResolver::class)->resolve($quote->opportunity),
            'workflow_status' => $status === null ? null : [
                'id' => $status->id,
                'name' => $status->name,
                'color' => $status->color,
            ],
            'operator' => $this->summarizeOperator($quote->supervisor),
        ];
    }

    /**
     * @return array{registry: array{id: int, name: string}|null, product_categories: array<int, array{id: int, name: string}>, status: array{source: string, distinct_count: int, entries: array<int, array{id: int, name: string, color: string|null, group: string, count: int}>}, operator: array{id: int, name: string, avatar_url: string|null}|null}
     */
    private function contextForOpportunity(Opportunity $opportunity): array
    {
        return [
            'registry' => $this->summarizeByName($opportunity->registry),
            'product_categories' => $this->summarizeProductCategories($opportunity->productLines),
            'status' => app(OpportunityStatusResolver::class)->resolve($opportunity),
            'operator' => $this->summarizeOperator($opportunity->operatorManager()),
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function summarizeByName(?Model $related): ?array
    {
        return $related === null ? null : ['id' => $related->id, 'name' => $related->name];
    }

    /**
     * Deduplicated by category id: the same category can repeat across
     * `productLines` rows paired with a different business function.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function summarizeProductCategories(iterable $productLines): array
    {
        return $this->summarizeCategories(
            collect($productLines)->map(static fn (Model $line): ?Model => $line->productCategory),
        );
    }

    /**
     * Shared tail of both origins' category projection: an Opportunity reads
     * them off its `product_lines` pivot, an Offerta off its revenue lines'
     * products — two different paths to the same `{id, name}` list.
     *
     * @param  Collection<int, Model|null>  $categories
     * @return array<int, array{id: int, name: string}>
     */
    private function summarizeCategories(Collection $categories): array
    {
        return $categories
            ->filter()
            ->unique('id')
            ->map(fn (Model $category): array => ['id' => $category->id, 'name' => $category->name])
            ->values()
            ->all();
    }

    /**
     * @return array{id: int, name: string, avatar_url: string|null}|null
     */
    private function summarizeOperator(?User $operator): ?array
    {
        return $operator === null ? null : [
            'id' => $operator->id,
            'name' => $operator->name,
            'avatar_url' => $operator->avatarDataUri(),
        ];
    }
}
