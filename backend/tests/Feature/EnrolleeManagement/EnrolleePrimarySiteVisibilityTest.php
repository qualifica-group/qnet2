<?php

declare(strict_types=1);

use App\Models\EmploymentProfile;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use App\RequestManagement\EnrolleeManagementNotable;
use App\RequestManagement\RequestModule;
use App\Services\RequestManagement\RequestManagementScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Spec 0165: `enrollee-management.viewPrimarySite` opens the enrollees of the
 * actor's PHYSICAL Sede only, in union with the offers they operate.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->validated = QuoteWorkflowStatus::query()->whereNull('quote_workflow_id')->where('system_key', 'validated')->value('id')
        ?? QuoteWorkflowStatus::factory()->global()->system('validated')->create()->id;
    $this->physical = OperationalSite::factory()->create();
    $this->remote = OperationalSite::factory()->create();
});

if (! function_exists('primarySiteActor')) {
    /**
     * @param  array<int, string>  $abilities  enrollee-management abilities
     */
    function primarySiteActor(array $abilities, ?OperationalSite $physical, OperationalSite ...$remote): User
    {
        foreach (['viewAny', 'view', 'viewAll', 'viewSite', 'viewPrimarySite'] as $ability) {
            Permission::findOrCreate("enrollee-management.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo(array_map(static fn (string $ability): string => "enrollee-management.{$ability}", $abilities));

        $profile = EmploymentProfile::factory();
        $profile = $physical === null ? $profile : $profile->physicalSite($physical);
        $profile->remoteSites(...$remote)->create(['user_id' => $user->id]);

        return $user;
    }
}

if (! function_exists('primarySiteQuote')) {
    function primarySiteQuote(int $statusId, ?int $siteId, ?int $operatorId = null): Quote
    {
        return Quote::factory()->create([
            'quote_workflow_status_id' => $statusId,
            'operational_site_id' => $siteId,
            'operator_id' => $operatorId,
        ]);
    }
}

it('AC-001: permissions:sync mints enrollee-management.viewPrimarySite only', function (): void {
    $this->artisan('permissions:sync')->assertSuccessful();

    expect(Permission::where('name', 'enrollee-management.viewPrimarySite')->exists())->toBeTrue()
        ->and(Permission::where('name', 'request-management.viewPrimarySite')->exists())->toBeFalse()
        ->and(RequestModule::Enrollees->hasPrimarySiteTier())->toBeTrue()
        ->and(RequestModule::Requests->hasPrimarySiteTier())->toBeFalse();
});

it('AC-002: viewPrimarySite adds the physical Sede to the own rows, never a remote Sede, another Sede or no Sede', function (): void {
    $actor = primarySiteActor(['viewAny', 'viewPrimarySite'], $this->physical, $this->remote);

    $own = primarySiteQuote($this->validated, null, $actor->id);
    $physical = primarySiteQuote($this->validated, $this->physical->id);
    $remote = primarySiteQuote($this->validated, $this->remote->id);
    $other = primarySiteQuote($this->validated, OperationalSite::factory()->create()->id);
    $noSite = primarySiteQuote($this->validated, null);

    $ids = RequestManagementScope::scopeToActor(Quote::query(), $actor, RequestModule::Enrollees)->pluck('id')->sort()->values()->all();

    expect($ids)->toBe(collect([$own->id, $physical->id])->sort()->values()->all())
        ->and($ids)->not->toContain($remote->id, $other->id, $noSite->id);
});

it('AC-003: assertInScope(Enrollees) allows the physical Sede and denies the remote one', function (): void {
    $actor = primarySiteActor(['viewAny', 'viewPrimarySite'], $this->physical, $this->remote);
    $scope = new RequestManagementScope;

    $scope->assertInScope($actor, primarySiteQuote($this->validated, $this->physical->id), RequestModule::Enrollees);

    expect(fn () => $scope->assertInScope($actor, primarySiteQuote($this->validated, $this->remote->id), RequestModule::Enrollees))
        ->toThrow(HttpException::class);
});

it('AC-003: viewSite still covers every membership, remote included', function (): void {
    $actor = primarySiteActor(['viewAny', 'viewSite'], $this->physical, $this->remote);

    expect(RequestManagementScope::visibleSiteIds($actor, RequestModule::Enrollees))
        ->toEqualCanonicalizing([$this->physical->id, $this->remote->id]);
});

it('AC-004: without a physical Sede, viewPrimarySite widens nothing', function (): void {
    $actor = primarySiteActor(['viewAny', 'viewPrimarySite'], null, $this->remote);
    $own = primarySiteQuote($this->validated, $this->remote->id, $actor->id);
    primarySiteQuote($this->validated, $this->remote->id);

    expect(RequestManagementScope::scopeToActor(Quote::query(), $actor, RequestModule::Enrollees)->pluck('id')->all())
        ->toBe([$own->id]);
});

it('AC-005: mentionable users include a viewPrimarySite holder whose physical Sede hosts an offer, not a remote member', function (): void {
    $opportunity = Opportunity::factory()->create();
    Quote::factory()->create([
        'opportunity_id' => $opportunity->id,
        'quote_workflow_status_id' => $this->validated,
        'operational_site_id' => $this->physical->id,
    ]);

    $physicalMember = primarySiteActor(['view', 'viewPrimarySite'], $this->physical);
    $remoteMember = primarySiteActor(['view', 'viewPrimarySite'], $this->remote, $this->physical);

    $ids = app(EnrolleeManagementNotable::class)->mentionableUsersQuery($opportunity)->pluck('id')->all();

    expect($ids)->toContain($physicalMember->id)->not->toContain($remoteMember->id);
});
