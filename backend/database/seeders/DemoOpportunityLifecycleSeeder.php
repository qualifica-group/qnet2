<?php

namespace Database\Seeders;

use App\Enums\WorkflowStatusGroup;
use App\Models\Opportunity;
use App\Models\OpportunityWorkflowStatus;
use App\Models\User;
use App\RequestManagement\ApplicableAttribute;
use App\Services\RequestManagement\RequestManagementService;
use App\Services\RoleAssignmentGuard;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Walks the seeded opportunities through their "stati di lavorazione" (spec
 * 0047) and fills the OPPORTUNITY-context attributes their categories carry
 * (spec 0061), so the demo shows the whole lifecycle — from the initial open
 * row to a validated/closed one — instead of a grid where every request sits
 * on its creation state with an empty work panel.
 *
 * Everything goes through RequestManagementService::updateWork(), the same
 * path PATCH /api/request-management/{opportunity} uses: set membership
 * against the RESOLVED workflow, the mandatory note on a `requires_note`
 * advance (created through the real collaborative-notes path), attribute-value
 * validation and the activity log all run exactly as they do for an operator.
 *
 * Each request is moved to the status at its own index in the resolved set
 * (modulo its size), so consecutive rows spread over the whole pick list and
 * every state of every seeded workflow ends up represented in the grid.
 *
 * Depends on DemoOpportunitySeeder (the rows), DemoCategoryWorkflowSeeder (the
 * pick lists) and DemoProductCategorySeeder (the attributes) — must run after
 * all three. A no-op without a privileged actor: a `requires_note` advance
 * creates a note, which is permission-checked like any other write.
 */
class DemoOpportunityLifecycleSeeder extends Seeder
{
    private const int SEED = 20260729;

    /** How often a still-open/pending request also gets a planned callback. */
    private const int CALLBACK_PROBABILITY = 40;

    private const int CALLBACK_MIN_DAYS = 1;

    private const int CALLBACK_MAX_DAYS = 30;

    public function __construct(private readonly RequestManagementService $requests) {}

    public function run(): void
    {
        $actor = $this->resolveActor();

        if ($actor === null) {
            return;
        }

        $faker = FakerFactory::create('it_IT');
        $faker->seed(self::SEED);

        foreach (Opportunity::query()->orderBy('id')->get() as $index => $opportunity) {
            $this->advance($opportunity, $actor, $faker, $index);
        }
    }

    /**
     * The account the working advances are attributed to: the privileged demo
     * user, or any other user allowed to write notes.
     */
    private function resolveActor(): ?User
    {
        // `whereHas`, not the spatie `role()` scope: that one throws when the
        // role itself does not exist yet (partial run), which is exactly the
        // case this method has to survive.
        $privileged = User::query()
            ->whereHas('roles', static fn ($query) => $query->where('name', RoleAssignmentGuard::PRIVILEGED_ROLE))
            ->orderBy('id')
            ->first();

        if ($privileged !== null) {
            return $privileged;
        }

        return User::query()->orderBy('id')->get()->first(
            static fn (User $user): bool => $user->can('notes.create'),
        );
    }

    private function advance(Opportunity $opportunity, User $actor, Generator $faker, int $index): void
    {
        $panel = $this->requests->loadWorkPanel($opportunity);
        /** @var Collection<int, OpportunityWorkflowStatus> $statuses */
        $statuses = $panel['workflow_statuses'];

        if ($statuses->isEmpty()) {
            return;
        }

        /** @var OpportunityWorkflowStatus $target */
        $target = $statuses->values()[$index % $statuses->count()];

        $this->requests->updateWork($opportunity, $actor, $this->payload($target, $panel['applicable_attributes'], $faker));
    }

    /**
     * @param  Collection<int, ApplicableAttribute>  $applicableAttributes
     * @return array<string, mixed>
     */
    private function payload(OpportunityWorkflowStatus $target, Collection $applicableAttributes, Generator $faker): array
    {
        $payload = ['opportunity_workflow_status_id' => $target->id];

        if ($target->requires_note) {
            // Spec 0054 D-5: the advance is rejected without it.
            $payload['note'] = $faker->sentence(10);
        }

        $values = $this->attributeValues($applicableAttributes, $faker);

        if ($values !== []) {
            $payload['attribute_values'] = $values;
        }

        $callbackAt = $this->maybeCallbackAt($target, $faker);

        if ($callbackAt !== null) {
            $payload['next_callback_at'] = $callbackAt;
        }

        return $payload;
    }

    /**
     * A planned callback only makes sense while the request is still being
     * worked: a closed/validated one is done (spec 0052, D-1).
     */
    private function maybeCallbackAt(OpportunityWorkflowStatus $target, Generator $faker): ?string
    {
        $isWorking = in_array($target->group, [WorkflowStatusGroup::Open, WorkflowStatusGroup::Pending], true);

        if (! $isWorking || ! $faker->boolean(self::CALLBACK_PROBABILITY)) {
            return null;
        }

        return now()
            ->addDays($faker->numberBetween(self::CALLBACK_MIN_DAYS, self::CALLBACK_MAX_DAYS))
            ->setTime($faker->numberBetween(9, 17), $faker->randomElement([0, 15, 30, 45]))
            ->format('Y-m-d H:i:s');
    }

    /**
     * A plausible value per applicable attribute, keyed by `code` exactly like
     * the work panel submits them. A type with no safe demo value (a
     * `relation`, whose value is an id of another entity) is skipped: the
     * field stays empty rather than pointing at a row picked at random.
     *
     * @param  Collection<int, ApplicableAttribute>  $applicableAttributes
     * @return array<string, mixed>
     */
    private function attributeValues(Collection $applicableAttributes, Generator $faker): array
    {
        $values = [];

        foreach ($applicableAttributes as $attribute) {
            $value = $this->attributeValue($attribute, $faker);

            if ($value !== null) {
                $values[$attribute->code] = $value;
            }
        }

        return $values;
    }

    private function attributeValue(ApplicableAttribute $attribute, Generator $faker): mixed
    {
        return match ($attribute->type) {
            'text', 'textarea' => $this->stringValue($attribute, $faker),
            'integer' => $faker->numberBetween($this->min($attribute, 1), $this->max($attribute, 40)),
            'decimal' => $faker->randomFloat(2, $this->min($attribute, 1), $this->max($attribute, 5000)),
            'boolean' => $faker->boolean(),
            'enum' => $this->enumValue($attribute, $faker),
            'date' => $faker->dateTimeBetween('-2 months', '+2 months')->format('Y-m-d'),
            'datetime' => $faker->dateTimeBetween('-2 months', '+2 months')->format('Y-m-d\TH:i'),
            'time' => sprintf('%02d:%02d', $faker->numberBetween(8, 18), $faker->randomElement([0, 30])),
            'email' => $faker->safeEmail(),
            'url' => 'https://'.$faker->domainName(),
            'color' => $faker->hexColor(),
            default => null,
        };
    }

    /**
     * Kept short on purpose: a `maxLength` config below a generated sentence
     * would fail validation for the whole request.
     */
    private function stringValue(ApplicableAttribute $attribute, Generator $faker): string
    {
        $value = $faker->sentence(6);
        $maxLength = $attribute->config['maxLength'] ?? null;

        return $maxLength === null ? $value : mb_substr($value, 0, (int) $maxLength);
    }

    /**
     * One of the attribute's own options — a multiselect one takes a
     * single-element array, the shape its rules expect.
     */
    private function enumValue(ApplicableAttribute $attribute, Generator $faker): string|array|null
    {
        $optionValues = array_column($attribute->options, 'value');

        if ($optionValues === []) {
            return null;
        }

        $picked = $faker->randomElement($optionValues);

        return ($attribute->config['display'] ?? null) === 'multiselect' ? [$picked] : $picked;
    }

    private function min(ApplicableAttribute $attribute, int $fallback): int
    {
        return (int) ($attribute->config['min'] ?? $fallback);
    }

    private function max(ApplicableAttribute $attribute, int $fallback): int
    {
        return (int) ($attribute->config['max'] ?? $fallback);
    }
}
