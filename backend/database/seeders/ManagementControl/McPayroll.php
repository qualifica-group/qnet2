<?php

namespace Database\Seeders\ManagementControl;

use App\Enums\RelationshipTypeEnum;
use App\Enums\SalaryPeriodEnum;
use App\Models\BusinessFunction;
use App\Models\EmploymentContract;
use App\Models\User;
use App\Services\EmploymentContracts\EmploymentContractService;
use Carbon\CarbonImmutable;
use Faker\Generator;
use Illuminate\Support\Collection;

/**
 * The staff of the dataset and what their hours cost (spec 0277): every active user
 * with an employment profile gets a business function when it has none, a hire date
 * (most before the period, a quarter hired during it) and a contract history with a
 * monthly gross salary from the hire date, plus the CCNL raise on 2026-01-01 for
 * whoever was already there. The history is rebuilt on every run.
 */
final class McPayroll
{
    private const int HIRED_DURING_PERIOD_PERCENT = 25;

    private const string EARLIEST_HIRE = '2015-01-01';

    private const string LATEST_HIRE = '2026-09-01';

    private const int SALARY_STEP = 50;

    private const int STANDARD_DAILY_MINUTES = 480;

    private const string RAISE_REASON = 'Rinnovo CCNL 2026';

    public function __construct(private readonly EmploymentContractService $contracts) {}

    /**
     * @param  list<array{company_id: int, company_site_id: int|null, bank_id: int}>  $companies
     * @return list<array{user_id: int, hired_at: string, delivery: bool, daily_minutes: int}> the roster
     */
    public function handle(Generator $faker, array $companies): array
    {
        $functions = BusinessFunction::query()->orderBy('id')->get(['id', 'name']);
        $users = User::query()->where('is_active', true)->whereHas('employment')->with(['employment', 'businessFunctions'])->orderBy('id')->get();
        $roster = [];

        foreach ($users as $index => $user) {
            // Step 1: the function membership the generic hours are charged to (spec 0282)
            $memberships = $this->ensureMembership($faker, $user, $functions);

            // Step 2: hire date and contract history with salary
            $hiredAt = $this->hireDate($faker, $index);
            $companyId = $companies[$index % 4 === 3 && count($companies) > 1 ? 1 : 0]['company_id'];
            $this->rebuildContracts($user, $hiredAt, $this->monthlySalary($faker, $memberships), $companyId);

            $roster[] = [
                'user_id' => $user->id,
                'hired_at' => $hiredAt,
                'delivery' => $memberships->intersect(McCatalogue::DELIVERY_FUNCTIONS)->isNotEmpty(),
                'daily_minutes' => self::STANDARD_DAILY_MINUTES,
            ];
        }

        return $roster;
    }

    /**
     * @param  Collection<int, BusinessFunction>  $functions
     * @return Collection<int, string> the names of the user's functions
     */
    private function ensureMembership(Generator $faker, User $user, Collection $functions): Collection
    {
        if ($user->businessFunctions->isEmpty()) {
            $user->businessFunctions()->sync([$faker->randomElement($functions->all())->id]);
            $user->load('businessFunctions');
        }

        return $user->businessFunctions->pluck('name');
    }

    /** The first user (the demo admin) and most others were already there; a quarter joins during the period. */
    private function hireDate(Generator $faker, int $index): string
    {
        $duringPeriod = $index > 0 && $faker->boolean(self::HIRED_DURING_PERIOD_PERCENT);
        $date = $duringPeriod
            ? $faker->dateTimeBetween(McCatalogue::PERIOD_START, self::LATEST_HIRE)
            : $faker->dateTimeBetween(self::EARLIEST_HIRE, '2024-12-01');

        return McTimeline::nextWorkingDay(CarbonImmutable::instance($date))->toDateString();
    }

    /**
     * The highest band among the user's functions.
     *
     * @param  Collection<int, string>  $functionNames
     */
    private function monthlySalary(Generator $faker, Collection $functionNames): int
    {
        [$min, $max] = $functionNames
            ->map(fn (string $name): array => McCatalogue::SALARY_BY_FUNCTION[$name] ?? McCatalogue::DEFAULT_SALARY)
            ->sortByDesc(fn (array $band): int => $band[1])
            ->first();

        return (int) (round($faker->numberBetween($min, $max) / self::SALARY_STEP) * self::SALARY_STEP);
    }

    private function rebuildContracts(User $user, string $hiredAt, int $salary, int $companyId): void
    {
        $profile = $user->employment;
        EmploymentContract::query()->where('employment_profile_id', $profile->id)->delete();
        $profile->update(['hired_at' => $hiredAt, 'standard_daily_minutes' => self::STANDARD_DAILY_MINUTES]);

        $condition = [
            'relationship_type' => RelationshipTypeEnum::Employee->value,
            'company_id' => $companyId,
            'salary_period' => SalaryPeriodEnum::Monthly->value,
        ];
        $this->contracts->create($profile, [...$condition, 'starts_on' => $hiredAt, 'salary_amount' => $salary, 'change_reason' => 'Assunzione']);

        if ($hiredAt < McCatalogue::RAISE_ON) {
            $raised = round($salary * (1 + McCatalogue::RAISE_PERCENT / 100), 2);
            $this->contracts->create($profile, [...$condition, 'starts_on' => McCatalogue::RAISE_ON, 'salary_amount' => $raised, 'change_reason' => self::RAISE_REASON]);
        }
    }
}
