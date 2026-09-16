<?php

use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Source;
use App\Models\User;
use App\Services\NavigationService;
use Database\Seeders\QualificaOperatorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * User directive 2026-09-16: these operators convert a lead into an
 * opportunity. They are every member of the three roles holding the
 * LEAD_CONVERSION block (supervisore, coordinatore, marketing).
 */
const LEAD_CONVERTERS = [
    'michela.fabozzi@qualificagroup.com',
    'rosa.falzarano@qualificagroup.com',
    'fabrizio.aliberti@qualificagroup.com',
    'umberto.santamaria@qualificagroup.com',
    'simona.chiacchio@qualificagroup.com',
    'sabino.figurelli@qualificagroup.com',
];

it('grants the lead conversion to the listed operators, not the opportunity module', function () {
    $this->seed(QualificaOperatorSeeder::class);

    foreach (LEAD_CONVERTERS as $email) {
        $user = User::query()->where('email', $email)->firstOrFail();

        expect($user->can('opportunities.create'))->toBeTrue("{$email} create")
            ->and($user->can('opportunities.viewAny'))->toBeTrue("{$email} viewAny")
            ->and($user->can('opportunities.view'))->toBeFalse("{$email} view")
            ->and($user->can('opportunities.update'))->toBeFalse("{$email} update")
            ->and($user->can('opportunities.delete'))->toBeFalse("{$email} delete");
    }
});

it('keeps the commercial roles out of the lead conversion', function () {
    $this->seed(QualificaOperatorSeeder::class);

    foreach (['marco.baldi@qualificagroup.com', 'gaetano.dellaporta@qualificagroup.com', 'marlena.jaruga@qualificagroup.com'] as $email) {
        expect(User::query()->where('email', $email)->firstOrFail()->can('opportunities.create'))->toBeFalse($email);
    }
});

it('lets the listed operators run both conversion paths end to end', function () {
    $this->seed(QualificaOperatorSeeder::class);

    foreach (LEAD_CONVERTERS as $email) {
        Sanctum::actingAs(User::query()->where('email', $email)->firstOrFail());

        // Single lead: the prefilled form's defaults and its field envelope.
        $single = Lead::factory()->create(['source_id' => Source::factory()]);
        $this->getJson("/api/leads/{$single->id}/opportunity-defaults")->assertOk();
        $this->getJson('/api/meta/opportunities')->assertOk();

        // Mass action: derived server-side.
        $this->postJson('/api/leads/convert-to-opportunities', ['lead_ids' => [$single->id]])->assertOk();
        expect(Opportunity::query()->where('lead_id', $single->id)->count())->toBe(1, $email);
    }
});

it('keeps the opportunity menu entry hidden from the listed operators', function () {
    $this->seed(QualificaOperatorSeeder::class);

    foreach (LEAD_CONVERTERS as $email) {
        $menu = app(NavigationService::class)->for(User::query()->where('email', $email)->firstOrFail());

        expect(json_encode($menu))->not->toContain('"\\/opportunities"');
    }
});
