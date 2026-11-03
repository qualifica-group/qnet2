<?php

use App\Models\Campaign;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\Project;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Quote;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\AiAssistant\Mentions\AiMentionNotFoundException;
use App\Services\AiAssistant\Mentions\AiMentionRegistry;
use App\Services\AiAssistant\Mentions\AiMentionResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Spec 0347, AC-009..AC-011: a mentioned record becomes a compact, allow-listed
 * projection, authorized per record, with no lazy loading and no secret.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    // The app does not enable it globally: the projection must stay query-free on relations.
    Model::preventLazyLoading();
    $this->registry = app(AiMentionRegistry::class);
    $this->resolver = app(AiMentionResolver::class);
});

afterEach(fn () => Model::preventLazyLoading(false));

function mentionSuperAdmin(): User
{
    Role::query()->firstOrCreate(['name' => 'super-admin']);

    return tap(User::factory()->create())->assignRole('super-admin');
}

/**
 * module => factory-made record (one per mentionable module).
 *
 * @return array<string, Closure(): Model>
 */
dataset('mention modules', [
    'companies' => ['companies', fn () => Company::factory()->create()],
    'company-sites' => ['company-sites', fn () => CompanySite::factory()->create()],
    'referents' => ['referents', fn () => Referent::factory()->create()],
    'registries' => ['registries', fn () => Registry::factory()->create()],
    'leads' => ['leads', fn () => Lead::factory()->create()],
    'opportunities' => ['opportunities', fn () => Opportunity::factory()->create()],
    'campaigns' => ['campaigns', fn () => Campaign::factory()->create()],
    'quotes' => ['quotes', fn () => Quote::factory()->create()],
    'work-orders' => ['work-orders', fn () => WorkOrder::factory()->create()],
    'projects' => ['projects', fn () => Project::factory()->create()],
    'tasks' => ['tasks', fn () => Task::factory()->create()],
    'products' => ['products', fn () => Product::factory()->create()],
    'invoices' => ['invoices', fn () => Invoice::factory()->create()],
    'purchase-invoices' => ['purchase-invoices', fn () => PurchaseInvoice::factory()->create()],
    'purchase-orders' => ['purchase-orders', fn () => PurchaseOrder::factory()->create()],
    'purchase-requests' => ['purchase-requests', fn () => PurchaseRequest::factory()->create()],
    'contracts' => ['contracts', fn () => Contract::factory()->create()],
    'users' => ['users', fn () => User::factory()->create()],
]);

it('lists the 18 spec modules', function () {
    expect($this->registry->modules())->toEqualCanonicalizing([
        'companies', 'company-sites', 'referents', 'registries', 'leads', 'opportunities', 'campaigns', 'quotes', 'work-orders',
        'projects', 'tasks', 'products', 'invoices', 'purchase-invoices', 'purchase-orders', 'purchase-requests', 'contracts', 'users',
    ])->and($this->registry->has('quotes'))->toBeTrue()->and($this->registry->has('nope'))->toBeFalse();
});

it('resolves a factory record of every module into a non-empty projection without lazy loading', function (string $module, Closure $make) {
    $record = $make();

    $mention = $this->resolver->resolve(mentionSuperAdmin(), $module, $record->getKey());

    expect($mention->module)->toBe($module)
        ->and($mention->id)->toBe($record->getKey())
        ->and($mention->label)->not->toBe('')
        ->and(json_decode($mention->context, true))->toHaveKey('id', $record->getKey())->and(json_decode($mention->context, true))->not->toHaveCount(1);
})->with('mention modules');

it('projects the relations as names, not as nested models', function () {
    $registry = Registry::factory()->create(['name' => 'Acme Srl']);
    $lead = Lead::factory()->create(['registry_id' => $registry->id]);

    $mention = $this->resolver->resolve(mentionSuperAdmin(), 'leads', $lead->id);

    expect($mention->label)->toBe('Acme Srl')
        ->and(json_decode($mention->context, true)['registry'])->toBe('Acme Srl');
});

it('never projects password, remember token or anything outside the allow-list for a user', function () {
    $user = User::factory()->create(['name' => 'Mario Rossi']);

    $mention = $this->resolver->resolve(mentionSuperAdmin(), 'users', $user->id);
    $context = json_decode($mention->context, true);

    expect($mention->label)->toBe('Mario Rossi')
        ->and(array_keys($context))->not->toContain('password', 'remember_token', 'module_open_preferences')
        ->and($mention->context)->not->toContain($user->password);
});

it('truncates the projection to mention_max_chars', function () {
    config()->set('ai-assistant.mention_max_chars', 60);
    $company = Company::factory()->create(['denomination' => str_repeat('A', 200)]);

    $mention = $this->resolver->resolve(mentionSuperAdmin(), 'companies', $company->id);

    expect(mb_strlen($mention->context))->toBe(60)
        ->and($mention->context)->toEndWith(AiMentionResolver::TRUNCATION_MARKER);
});

it('throws not found for an unknown module and for a missing record', function () {
    $actor = mentionSuperAdmin();

    expect(fn () => $this->resolver->resolve($actor, 'nope', 1))->toThrow(AiMentionNotFoundException::class);
    expect(fn () => $this->resolver->resolve($actor, 'companies', 999999))->toThrow(AiMentionNotFoundException::class);
});

it('denies a record the policy view does not allow (task outside the actor visibility)', function () {
    Permission::findOrCreate('tasks.view');
    $actor = User::factory()->create();
    $actor->givePermissionTo('tasks.view');
    $foreign = Task::factory()->create();

    expect(fn () => $this->resolver->resolve($actor, 'tasks', $foreign->id))->toThrow(AuthorizationException::class);
});

it('denies a module the actor holds no view permission on', function () {
    Permission::findOrCreate('companies.view');
    $company = Company::factory()->create();

    expect(fn () => $this->resolver->resolve(User::factory()->create(), 'companies', $company->id))->toThrow(AuthorizationException::class);
});

it('lists only the modules the user may view', function () {
    foreach (['companies', 'quotes'] as $module) {
        Permission::findOrCreate("{$module}.view");
    }
    $actor = User::factory()->create();
    $actor->givePermissionTo('companies.view');

    expect($this->registry->availableModules($actor))->toBe(['companies'])
        ->and($this->registry->availableModules(mentionSuperAdmin()))->toHaveCount(18);
});
