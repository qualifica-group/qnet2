<?php

namespace Database\Seeders;

use App\Models\Opportunity;
use App\Models\User;
use App\Services\RequestManagement\RequestManagementService;
use App\Services\RoleAssignmentGuard;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;

/**
 * Plans a callback on some of the seeded requests (spec 0052), so the demo
 * grid shows worked requests instead of rows with an empty work panel.
 *
 * Spec 0083 (D-2) REMOVED the "stato di lavorazione" from the Opportunity —
 * the operational status lives on the Offerta now, and the work panel exposes
 * no status set at all. This seeder used to walk each request through that
 * set (and to create the mandatory note on a `requires_note` advance); that
 * half is gone, not ported: replaying it on the Offerta belongs to a quote
 * lifecycle seeder, not to this one, and faking a status the record no longer
 * has would seed data the product cannot represent.
 *
 * Spec 0084 (D-1) REMOVED the dynamic "Informazioni aggiuntive" values this
 * seeder used to fake-fill on the Opportunity (`RequestManagementService::
 * loadWorkPanel()` no longer resolves an applicable set at all) — that
 * concern moved to the Offerta, see DemoQuoteSeeder's own `attribute_values`
 * fake (AC-050).
 *
 * The remaining write still goes through RequestManagementService::updateWork(),
 * the same path PATCH /api/request-management/{opportunity} uses, so the
 * activity log runs exactly as it does for an operator.
 *
 * Depends on DemoOpportunitySeeder (the rows) — must run after it. A no-op
 * without a privileged actor.
 */
class DemoOpportunityLifecycleSeeder extends Seeder
{
    private const int SEED = 20260729;

    /** How often a request gets a planned callback. */
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
        $callbackAt = $this->maybeCallbackAt($faker, $index);

        if ($callbackAt === null) {
            return;
        }

        $this->requests->updateWork($opportunity, $actor, ['next_callback_at' => $callbackAt]);
    }

    /**
     * The demo only needs SOME requests to carry a planned callback and
     * others not — approximated on the row index (spec 0083 removed the
     * working state this used to branch on, see class docblock).
     */
    private function maybeCallbackAt(Generator $faker, int $index): ?string
    {
        if ($index % 2 !== 0 || ! $faker->boolean(self::CALLBACK_PROBABILITY)) {
            return null;
        }

        return now()
            ->addDays($faker->numberBetween(self::CALLBACK_MIN_DAYS, self::CALLBACK_MAX_DAYS))
            ->setTime($faker->numberBetween(9, 17), $faker->randomElement([0, 15, 30, 45]))
            ->format('Y-m-d H:i:s');
    }
}
