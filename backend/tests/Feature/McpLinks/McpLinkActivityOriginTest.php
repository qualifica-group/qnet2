<?php

use App\Models\Campaign;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(fn () => config(['mcp-link.release_commit' => 'abc1234']));

/**
 * Spec 0434: a change made with a token issued for an MCP link (ChatGPT, Claude or another
 * MCP client acting for the user) is recorded with its link next to the causer, and the
 * activity log shows it as made with MCP AI. Every other change shows no origin.
 */
if (! function_exists('mcpOriginLeadUser')) {
    function mcpOriginLeadUser(): User
    {
        foreach (['create', 'view', 'viewActivity'] as $ability) {
            Permission::findOrCreate("leads.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo(['leads.create', 'leads.view', 'leads.viewActivity']);

        return $user;
    }
}

if (! function_exists('mcpOriginLeadPayload')) {
    /**
     * @return array<string, int>
     */
    function mcpOriginLeadPayload(): array
    {
        return [
            'registry_id' => Registry::factory()->create()->id,
            'campaign_id' => Campaign::factory()->create()->id,
            'source_id' => Source::factory()->create()->id,
        ];
    }
}

if (! function_exists('mcpOriginCreatedEntry')) {
    /**
     * The `created` entry of a lead, read through the activity log endpoint.
     *
     * @return array<string, mixed>
     */
    function mcpOriginCreatedEntry(int $leadId, string $token): array
    {
        return collect(callWithToken('GET', "/api/activity-log/leads/{$leadId}", $token)->assertOk()->json('data.items'))
            ->firstWhere('event', 'created');
    }
}

it('records the MCP link of a change made with a link token and shows it as made with MCP AI', function () {
    $user = mcpOriginLeadUser();
    [$key, $credential, , $link] = openLinkFor($user);
    $linkToken = callWithToken('POST', '/api/mcp/links/exchange', $key, ['credential' => $credential])->assertOk()->json('data.token');

    $leadId = callWithToken('POST', '/api/leads', $linkToken, mcpOriginLeadPayload())->assertCreated()->json('data.id');

    $activity = Activity::query()->where('subject_type', 'lead')->where('subject_id', $leadId)->latest('id')->firstOrFail();

    expect($activity->causer_id)->toBe($user->id)
        ->and($activity->properties->get('mcp_link_id'))->toBe($link->id)
        ->and(mcpOriginCreatedEntry($leadId, $user->createToken('app')->plainTextToken)['via'])->toBe('mcp');
});

it('marks an update made with a link token too, keeping the diff and the client of the request', function () {
    $user = mcpOriginLeadUser();
    foreach (['update', 'viewAll'] as $ability) {
        $user->givePermissionTo(Permission::findOrCreate("leads.{$ability}"));
    }
    [$key, $credential, , $link, $clientId] = openLinkFor($user);
    $linkToken = callWithToken('POST', '/api/mcp/links/exchange', $key, ['credential' => $credential])->assertOk()->json('data.token');
    $leadId = callWithToken('POST', '/api/leads', $linkToken, mcpOriginLeadPayload())->assertCreated()->json('data.id');

    callWithToken('PATCH', "/api/leads/{$leadId}", $linkToken, ['notes' => 'Richiamare lunedi'])->assertOk();

    $updated = Activity::query()->where('subject_type', 'lead')->where('subject_id', $leadId)->where('event', 'updated')->latest('id')->firstOrFail();
    $entry = collect(callWithToken('GET', "/api/activity-log/leads/{$leadId}", $user->createToken('app')->plainTextToken)->assertOk()->json('data.items'))
        ->firstWhere('event', 'updated');

    expect($updated->properties->get('mcp_link_id'))->toBe($link->id)
        ->and($updated->properties->get('api_client_id'))->toBe($clientId)
        ->and($updated->properties->get('attributes')['notes'])->toBe('Richiamare lunedi')
        ->and($updated->properties->has('old'))->toBeTrue()
        ->and($entry['via'])->toBe('mcp')
        ->and(collect($entry['changes'])->firstWhere('field', 'notes')['new_value'])->toBe('Richiamare lunedi');
});

it('shows no origin for a change made from the app', function () {
    $user = mcpOriginLeadUser();
    $appToken = $user->createToken('app')->plainTextToken;

    $leadId = callWithToken('POST', '/api/leads', $appToken, mcpOriginLeadPayload())->assertCreated()->json('data.id');

    $activity = Activity::query()->where('subject_type', 'lead')->where('subject_id', $leadId)->latest('id')->firstOrFail();
    $created = mcpOriginCreatedEntry($leadId, $appToken);

    expect($activity->properties->has('mcp_link_id'))->toBeFalse()
        ->and($created)->toHaveKey('via')
        ->and($created['via'])->toBeNull();
});

it('does not show a change made with the client key as made with MCP AI', function () {
    [, $key] = createApiClientWithKey();

    $leadId = callWithToken('POST', '/api/leads', $key, mcpOriginLeadPayload())->assertCreated()->json('data.id');

    $activity = Activity::query()->where('subject_type', 'lead')->where('subject_id', $leadId)->latest('id')->firstOrFail();

    expect($activity->properties->has('mcp_link_id'))->toBeFalse()
        ->and(mcpOriginCreatedEntry($leadId, $key)['via'])->toBeNull();
});
