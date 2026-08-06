<?php

namespace App\Http\Resources;

use App\Models\Opportunity;
use App\Models\Reward;
use App\Models\User;
use App\Services\Opportunities\OpportunityStatusResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
 * Extensibility of `context` (today only Opportunity, morph alias
 * 'opportunity'): buildContext()/resolveSourcePath() dispatch on the morph
 * map ALIAS (`getMorphClass()`, never a FQCN — `morph_map_is_strict`). A
 * second source type tomorrow adds one more `match` arm plus one more
 * `contextForX()` private method, without touching the rest of the
 * resource — not a speculative interface/strategy for a single real use
 * case today (engineering.md §1.3).
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
            'name' => $source->name,
            'path' => match ($alias) {
                'opportunity' => "/opportunities/{$source->id}",
                default => null,
            },
        ];
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
            default => null,
        };
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
        return collect($productLines)
            ->map(fn (Model $line): ?Model => $line->productCategory)
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
