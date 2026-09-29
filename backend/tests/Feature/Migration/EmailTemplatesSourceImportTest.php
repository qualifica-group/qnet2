<?php

use App\Enums\EmailTemplateModule;
use App\Enums\MigrationStatus;
use App\Jobs\RunMigrationJob;
use App\Models\EmailTemplate;
use App\Models\MigrationRun;
use App\Models\Role;
use App\Models\User;
use App\Services\MigrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

/*
|--------------------------------------------------------------------------
| `email-templates` migration source (spec 0175, D-13, AC-017)
|--------------------------------------------------------------------------
*/

uses(RefreshDatabase::class);

if (! function_exists('migrationsSuperAdminActor')) {
    function migrationsSuperAdminActor(): User
    {
        Role::query()->firstOrCreate(['name' => 'super-admin']);

        $actor = User::factory()->create();
        $actor->assignRole('super-admin');

        return $actor;
    }
}

if (! function_exists('runMigrationJobFor')) {
    function runMigrationJobFor(MigrationRun $run): void
    {
        (new RunMigrationJob($run->id))->handle(app(MigrationService::class));
    }
}

if (! function_exists('runEmailTemplatesImport')) {
    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    function runEmailTemplatesImport(array $items): MigrationRun
    {
        seedMigrationsConfig();

        Http::fake([
            fakeMigrationsBaseUrl().'/email-templates*' => Http::response([
                'items' => $items,
                'pagination' => ['total' => count($items)],
            ]),
        ]);

        $run = MigrationRun::factory()->create(['user_id' => migrationsSuperAdminActor()->id, 'source' => 'email-templates']);
        runMigrationJobFor($run);

        return $run->fresh();
    }
}

// ---------------------------------------------------------------------------
// AC-017 — happy path: name = subject = title, module work_orders, old_id
// ---------------------------------------------------------------------------

it('AC-017: creates an EmailTemplate with name=subject=title, module work_orders, sanitized body, old_id', function () {
    $run = runEmailTemplatesImport([
        ['id' => 12, 'title' => 'Conferma appuntamento', 'body' => '<p>Gentile cliente</p>'],
    ]);

    expect($run->status)->toBe(MigrationStatus::Completed)
        ->and($run->created_rows)->toBe(1)
        ->and($run->report)->toBeNull();

    $template = EmailTemplate::query()->where('old_id', 12)->first();
    expect($template)->not->toBeNull()
        ->and($template->name)->toBe('Conferma appuntamento')
        ->and($template->subject)->toBe('Conferma appuntamento')
        ->and($template->module)->toBe(EmailTemplateModule::WorkOrders)
        ->and($template->body)->toBe('<p>Gentile cliente</p>')
        ->and($template->is_active)->toBeTrue();
});

it('AC-017: re-importing the same template is idempotent (skip, no duplicate)', function () {
    runEmailTemplatesImport([['id' => 20, 'title' => 'Chiusura commessa', 'body' => '<p>Testo</p>']]);

    $second = runEmailTemplatesImport([['id' => 20, 'title' => 'Chiusura commessa', 'body' => '<p>Testo</p>']]);

    expect(EmailTemplate::query()->where('old_id', 20)->count())->toBe(1)
        ->and($second->skipped_rows)->toBe(1)
        ->and($second->created_rows)->toBe(0);
});

it('AC-017: body outside the current allow-list is sanitized with a warning', function () {
    $run = runEmailTemplatesImport([
        ['id' => 30, 'title' => 'Con tabella', 'body' => '<p>Testo</p><table><tr><td>cella</td></tr></table><script>alert(1)</script>'],
    ]);

    $template = EmailTemplate::query()->where('old_id', 30)->first();
    expect($template->body)->not->toContain('<table')
        ->and($template->body)->not->toContain('<script')
        ->and($template->body)->toContain('<p>Testo</p>');

    expect(collect($run->report)->firstWhere('level', 'warning')['message'])->toContain('body sanitized');
});

it('AC-017: a body already within the allow-list produces no warning', function () {
    $run = runEmailTemplatesImport([
        ['id' => 31, 'title' => 'Pulito', 'body' => '<p>Gia pulito</p>'],
    ]);

    expect($run->report)->toBeNull();
});

it('AC-017: name truncated to 191 characters with a warning, subject kept up to its own 255-char ceiling', function () {
    $longTitle = str_repeat('A', 300);

    $run = runEmailTemplatesImport([['id' => 40, 'title' => $longTitle, 'body' => '<p>x</p>']]);

    $template = EmailTemplate::query()->where('old_id', 40)->first();
    expect(mb_strlen($template->name))->toBe(191)
        ->and(mb_strlen($template->subject))->toBe(255);

    expect(collect($run->report)->pluck('message')->filter(fn (string $m) => str_contains($m, 'truncated'))->count())
        ->toBe(2);
});

it('AC-017: a name collision within the same module is resolved by a numeric suffix, never a row error', function () {
    EmailTemplate::factory()->create(['name' => 'Duplicato', 'module' => EmailTemplateModule::WorkOrders]);

    $run = runEmailTemplatesImport([['id' => 50, 'title' => 'Duplicato', 'body' => '<p>x</p>']]);

    expect($run->created_rows)->toBe(1)->and($run->failed_rows)->toBe(0);

    $template = EmailTemplate::query()->where('old_id', 50)->first();
    expect($template->name)->toBe('Duplicato (2)');

    expect(collect($run->report)->firstWhere('level', 'warning')['message'])->toContain('renamed to');
});

it('AC-017: a row with no title fails without blocking the rest of the run', function () {
    $run = runEmailTemplatesImport([
        ['id' => 60, 'title' => '', 'body' => '<p>x</p>'],
        ['id' => 61, 'title' => 'Valido', 'body' => '<p>x</p>'],
    ]);

    expect($run->created_rows)->toBe(1)->and($run->failed_rows)->toBe(1)
        ->and(EmailTemplate::query()->where('old_id', 61)->exists())->toBeTrue()
        ->and(EmailTemplate::query()->where('old_id', 60)->exists())->toBeFalse();
});
