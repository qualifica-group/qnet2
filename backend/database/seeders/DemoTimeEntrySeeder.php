<?php

namespace Database\Seeders;

use App\Models\TaskType;
use App\Models\TimeEntry;
use App\Models\TimeEntryDayNote;
use App\Models\User;
use Database\Seeders\Concerns\SeedsDevelopmentUsers;
use DateTimeImmutable;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Development seed for the segnatempo module (spec 0122): weekday entries
 * over the last few weeks for the demo account plus a handful of seeded
 * users, classified by the real `task_types` catalogue
 * (QualificaTaskTaxonomySeeder, which DemoDataSeeder runs before Tasks and
 * therefore before this step too).
 *
 * No `task_id`/`registry_id`/`opportunity_id`/`work_order_id` links are
 * seeded (D-5's server-side override only matters once a create path exists
 * to exercise it — MT-B1 is the schema/policy/navigation foundation, no
 * controller yet): every row here is a plain, unlinked segnatempo, which is
 * enough to populate the dashboard.
 *
 * Idempotent: clears its own rows for the users it seeds before writing, the
 * same pattern DemoTaskSeeder/DemoTaskNoteSeeder use — a re-run reproduces
 * the same dataset rather than piling duplicates on top of it.
 */
class DemoTimeEntrySeeder extends Seeder
{
    use SeedsDevelopmentUsers;

    /** How many seeded (non-demo) users get a demo segnatempo history. */
    private const int SEEDED_USERS = 4;

    /** How many past weeks (including the current one) get entries. */
    private const int WEEKS = 3;

    /** Chance a given weekday has at least one segnatempo. */
    private const float DAY_COVERAGE = 0.85;

    private const int MAX_ENTRIES_PER_DAY = 3;

    /** Chance an active day also carries a day note. */
    private const float DAY_NOTE_CHANCE = 0.2;

    private const array TITLES = [
        'Sviluppo', 'Allineamento', 'Chiamata cliente', 'Redazione offerta',
        'Analisi requisiti', 'Supporto', 'Formazione', 'Revisione codice',
    ];

    /** Fixed so a re-run reproduces the same dataset. */
    private const int FAKER_SEED = 20260914;

    public function run(): void
    {
        $taskTypes = TaskType::query()->where('is_active', true)->get();

        if ($taskTypes->isEmpty()) {
            return;
        }

        $users = $this->pickUsers();

        if ($users->isEmpty()) {
            return;
        }

        $faker = FakerFactory::create('it_IT');
        $faker->seed(self::FAKER_SEED);

        // Step 1: drop the previous run for exactly these users.
        $this->clearExisting($users);

        // Step 2: one weekday history per user.
        foreach ($users as $user) {
            $this->seedUserHistory($faker, $user, $taskTypes);
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function pickUsers()
    {
        $demoUser = User::query()->where('email', self::DEMO_EMAIL)->first();

        $seededUsers = User::query()
            ->where('email', 'like', '%@'.self::SEEDED_EMAIL_DOMAIN)
            ->orderBy('id')
            ->limit(self::SEEDED_USERS)
            ->get();

        return collect([$demoUser])->filter()->concat($seededUsers)->unique('id')->values();
    }

    /**
     * @param  Collection<int, User>  $users
     */
    private function clearExisting($users): void
    {
        $userIds = $users->pluck('id')->all();

        TimeEntry::query()->whereIn('user_id', $userIds)->delete();
        TimeEntryDayNote::query()->whereIn('user_id', $userIds)->delete();
    }

    /**
     * @param  Collection<int, TaskType>  $taskTypes
     */
    private function seedUserHistory(Generator $faker, User $user, $taskTypes): void
    {
        foreach ($this->weekdaysOfLastWeeks() as $date) {
            if (! $faker->boolean((int) (self::DAY_COVERAGE * 100))) {
                continue;
            }

            $this->seedDay($faker, $user, $taskTypes, $date);
        }
    }

    /**
     * @param  Collection<int, TaskType>  $taskTypes
     */
    private function seedDay(Generator $faker, User $user, $taskTypes, string $date): void
    {
        $entryCount = $faker->numberBetween(1, self::MAX_ENTRIES_PER_DAY);

        for ($index = 0; $index < $entryCount; $index++) {
            TimeEntry::factory()
                ->forUser($user)
                ->onDate($date)
                ->create([
                    'title' => $faker->randomElement(self::TITLES),
                    'task_type_id' => $taskTypes->random()->id,
                    'minutes' => $faker->randomElement([30, 45, 60, 90, 120]),
                ]);
        }

        if ($faker->boolean((int) (self::DAY_NOTE_CHANCE * 100))) {
            TimeEntryDayNote::query()->updateOrCreate(
                ['user_id' => $user->id, 'date' => $date],
                ['note' => $faker->sentence(8)],
            );
        }
    }

    /**
     * Every Monday-to-Friday date of the last WEEKS weeks, including the
     * current one, oldest first.
     *
     * @return array<int, string>
     */
    private function weekdaysOfLastWeeks(): array
    {
        $today = new DateTimeImmutable('today');
        $mondayThisWeek = $today->modify('monday this week');
        $firstMonday = $mondayThisWeek->modify(sprintf('-%d weeks', self::WEEKS - 1));

        $dates = [];

        for ($day = $firstMonday; $day <= $today; $day = $day->modify('+1 day')) {
            if ((int) $day->format('N') <= 5) {
                $dates[] = $day->format('Y-m-d');
            }
        }

        return $dates;
    }
}
