<?php

use App\Enums\SkillLevel;
use App\Models\BusinessFunction;
use App\Models\Skill;
use App\Models\User;
use App\Models\UserSkill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function coverageRows(array $query = []): array
{
    return test()->getJson('/api/user-skills/coverage?'.http_build_query($query))->assertOk()->json('data.rows');
}

function coverageStatuses(array $rows): array
{
    return collect($rows)->mapWithKeys(fn (array $row) => ["{$row['user']['name']}|{$row['skill']['name']}" => $row['status']])->all();
}

it('0286 AC-004: covered, below and missing against the required level, with the contract shape', function () {
    [$a, $b, $c, $d] = [Skill::factory()->create(['name' => 'A', 'category' => 'Cat']), Skill::factory()->create(['name' => 'B']), Skill::factory()->create(['name' => 'C']), Skill::factory()->create(['name' => 'D'])];
    $person = User::factory()->create(['name' => 'Anna']);
    skillProfile($person, null, [skillRequiredBy([[$a, 3], [$b, 3], [$c, 3], [$d, 1]])]);
    UserSkill::factory()->forUser($person)->atLevel(SkillLevel::Advanced)->create(['skill_id' => $a->id]);
    UserSkill::factory()->forUser($person)->atLevel(SkillLevel::Expert)->create(['skill_id' => $b->id]);
    $low = UserSkill::factory()->forUser($person)->atLevel(SkillLevel::Intermediate)->create(['skill_id' => $c->id]);

    Sanctum::actingAs(skillUserWith(['viewAll']));
    $rows = coverageRows();

    expect(coverageStatuses($rows))->toBe(['Anna|A' => 'covered', 'Anna|B' => 'covered', 'Anna|C' => 'below', 'Anna|D' => 'missing'])
        ->and($rows[2])->toBe([
            'user' => ['id' => $person->id, 'name' => 'Anna'],
            'skill' => ['id' => $c->id, 'name' => 'C', 'category' => null],
            'required_level' => 3, 'level' => 2, 'status' => 'below', 'user_skill_id' => $low->id,
        ])
        ->and($rows[3]['level'])->toBeNull()->and($rows[3]['user_skill_id'])->toBeNull()
        ->and($rows[0]['skill']['category'])->toBe('Cat');
});

it('0286 AC-004: the required level is the highest among the job roles', function () {
    $skill = Skill::factory()->create(['name' => 'A']);
    $person = User::factory()->create(['name' => 'Anna']);
    skillProfile($person, null, [skillRequiredBy([[$skill, 1]]), skillRequiredBy([[$skill, 4]]), skillRequiredBy([[$skill, 2]])]);
    UserSkill::factory()->forUser($person)->atLevel(SkillLevel::Advanced)->create(['skill_id' => $skill->id]);

    Sanctum::actingAs(skillUserWith(['viewAll']));
    $rows = coverageRows();

    expect($rows)->toHaveCount(1)->and($rows[0]['required_level'])->toBe(4)->and($rows[0]['status'])->toBe('below');
});

it('0286 AC-004: only active skills are required, a held unrequired skill stays out, order is person then skill', function () {
    $b = Skill::factory()->create(['name' => 'B']);
    $a = Skill::factory()->create(['name' => 'A']);
    $retired = Skill::factory()->inactive()->create(['name' => 'Retired']);
    $extra = Skill::factory()->create(['name' => 'Extra']);
    $zoe = User::factory()->create(['name' => 'Zoe']);
    $anna = User::factory()->create(['name' => 'Anna']);
    $gone = User::factory()->create(['name' => 'Gone', 'is_active' => false]);
    skillProfile($zoe, null, [skillRequiredBy([[$a, 1], [$b, 1], [$retired, 1]])]);
    skillProfile($anna, null, [skillRequiredBy([[$b, 1]])]);
    skillProfile($gone, null, [skillRequiredBy([[$a, 1]])]);
    UserSkill::factory()->forUser($zoe)->create(['skill_id' => $extra->id]);

    Sanctum::actingAs(skillUserWith(['viewAll']));

    expect(array_keys(coverageStatuses(coverageRows())))->toBe(['Anna|B', 'Zoe|A', 'Zoe|B']);
});

it('0286 AC-004: filters narrow by status, skill and business function', function () {
    $a = Skill::factory()->create(['name' => 'A']);
    $b = Skill::factory()->create(['name' => 'B']);
    $anna = User::factory()->create(['name' => 'Anna']);
    $bruno = User::factory()->create(['name' => 'Bruno']);
    $function = BusinessFunction::factory()->create();
    $bruno->businessFunctions()->attach($function->id);
    $role = skillRequiredBy([[$a, 1], [$b, 1]]);
    skillProfile($anna, null, [$role]);
    skillProfile($bruno, null, [$role]);
    UserSkill::factory()->forUser($anna)->create(['skill_id' => $a->id]);

    Sanctum::actingAs(skillUserWith(['viewAll']));

    expect(array_keys(coverageStatuses(coverageRows(['status' => 'covered']))))->toBe(['Anna|A'])
        ->and(array_keys(coverageStatuses(coverageRows(['status' => 'missing']))))->toBe(['Anna|B', 'Bruno|A', 'Bruno|B'])
        ->and(array_keys(coverageStatuses(coverageRows(['skill_id' => $b->id]))))->toBe(['Anna|B', 'Bruno|B'])
        ->and(array_keys(coverageStatuses(coverageRows(['business_function_id' => $function->id]))))->toBe(['Bruno|A', 'Bruno|B']);

    $this->getJson('/api/user-skills/coverage?status=bogus')->assertUnprocessable()->assertJsonValidationErrors('status');
});

it('0286 AC-004: the people follow the visibility of the actor', function () {
    $skill = Skill::factory()->create(['name' => 'C']);
    $manager = skillUserWith(['viewTeam'], ['name' => 'Manager']);
    $report = User::factory()->create(['name' => 'Report']);
    $stranger = User::factory()->create(['name' => 'Stranger']);
    $role = skillRequiredBy([[$skill, 1]]);
    foreach ([$manager, $report, $stranger] as $person) {
        skillProfile($person, $person->is($report) ? $manager : null, [$role]);
    }

    Sanctum::actingAs($manager);
    expect(array_keys(coverageStatuses(coverageRows())))->toBe(['Manager|C', 'Report|C']);

    $own = skillUserWith(['viewAny'], ['name' => 'Own']);
    skillProfile($own, null, [$role]);
    Sanctum::actingAs($own);
    expect(array_keys(coverageStatuses(coverageRows())))->toBe(['Own|C']);
});

it('0286 AC-004: the query count does not grow with the number of people', function () {
    $skills = Skill::factory()->count(3)->create()->all();
    $role = skillRequiredBy(array_map(fn (Skill $skill) => [$skill, 2], $skills));
    $countQueries = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        coverageRows();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };
    $enroll = function (int $people) use ($skills, $role): void {
        User::factory()->count($people)->create()->each(function (User $person) use ($skills, $role): void {
            skillProfile($person, null, [$role]);
            UserSkill::factory()->forUser($person)->create(['skill_id' => $skills[0]->id]);
        });
    };

    Sanctum::actingAs(skillUserWith(['viewAll']));
    $enroll(2);
    coverageRows(); // warm-up: permission and config caches fill on the first request
    $few = $countQueries();
    $enroll(15);

    expect($countQueries())->toBe($few);
});
