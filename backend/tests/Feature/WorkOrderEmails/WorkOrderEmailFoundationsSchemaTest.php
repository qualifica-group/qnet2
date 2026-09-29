<?php

use App\Enums\EmailTemplateModule;
use App\Enums\OutboundEmailStatus;
use App\Models\DocumentBundle;
use App\Models\EmailTemplate;
use App\Models\OutboundEmail;
use App\Models\WorkOrder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Foundations schema — spec 0175, AC-001
|--------------------------------------------------------------------------
|
| The three tables of the work-order-emails module exist with the columns
| of <data_model>, and each migration rolls back cleanly.
*/

uses(RefreshDatabase::class);

it('schema: email_templates has the data_model columns, unique(module, name), old_id unique (AC-001)', function () {
    foreach (['id', 'name', 'module', 'subject', 'body', 'description', 'is_active', 'old_id', 'created_at', 'updated_at'] as $column) {
        expect(Schema::hasColumn('email_templates', $column))->toBeTrue("missing column {$column}");
    }

    DB::table('email_templates')->insert([
        'name' => 'Conferma commessa', 'module' => 'work_orders', 'subject' => 'Conferma',
        'body' => '<p>Hi</p>', 'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(fn () => DB::table('email_templates')->insert([
        'name' => 'Conferma commessa', 'module' => 'work_orders', 'subject' => 'Conferma 2',
        'body' => '<p>Hi</p>', 'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    // Same name, different module: allowed (unique is composite).
    DB::table('email_templates')->insert([
        'name' => 'Conferma commessa', 'module' => 'opportunities', 'subject' => 'Conferma 2',
        'body' => '<p>Hi</p>', 'created_at' => now(), 'updated_at' => now(),
    ]);
    expect(DB::table('email_templates')->where('name', 'Conferma commessa')->count())->toBe(2);
});

it('migration: email_templates down() drops the table, up() recreates it (AC-001)', function () {
    $migration = require database_path('migrations/2026_09_28_150000_create_email_templates_table.php');

    $migration->down();
    expect(Schema::hasTable('email_templates'))->toBeFalse();

    $migration->up();
    expect(Schema::hasTable('email_templates'))->toBeTrue();
});

it('model: EmailTemplate casts module to the enum and is_active to bool (AC-001)', function () {
    $template = EmailTemplate::factory()->create(['module' => EmailTemplateModule::WorkOrders, 'is_active' => true]);
    $fresh = EmailTemplate::query()->findOrFail($template->id);

    expect($fresh->module)->toBe(EmailTemplateModule::WorkOrders)
        ->and($fresh->is_active)->toBeBool();
});

it('schema: document_bundles has the data_model columns, name unique, old_id unique (AC-001)', function () {
    foreach (['id', 'name', 'description', 'is_active', 'old_id', 'created_at', 'updated_at'] as $column) {
        expect(Schema::hasColumn('document_bundles', $column))->toBeTrue("missing column {$column}");
    }

    DB::table('document_bundles')->insert(['name' => 'Kit contratto', 'created_at' => now(), 'updated_at' => now()]);

    expect(fn () => DB::table('document_bundles')->insert(['name' => 'Kit contratto', 'created_at' => now(), 'updated_at' => now()]))
        ->toThrow(QueryException::class);
});

it('migration: document_bundles down() drops the table, up() recreates it (AC-001)', function () {
    $migration = require database_path('migrations/2026_09_28_150100_create_document_bundles_table.php');

    $migration->down();
    expect(Schema::hasTable('document_bundles'))->toBeFalse();

    $migration->up();
    expect(Schema::hasTable('document_bundles'))->toBeTrue();
});

it('model: DocumentBundle uses HasAttachments under the document_bundle alias (AC-001)', function () {
    $bundle = DocumentBundle::factory()->create();

    expect($bundle->getMorphClass())->toBe('document_bundle')
        ->and(config('attachments.attachable_types.document_bundle'))->toBe(DocumentBundle::class);
});

it('schema: outbound_emails has the data_model columns and the composite index (AC-001)', function () {
    foreach ([
        'id', 'emailable_type', 'emailable_id', 'email_template_id', 'status', 'sender_user_id',
        'from_address', 'to_recipients', 'cc_recipients', 'bcc_recipients', 'subject', 'body',
        'queued_at', 'sent_at', 'failed_at', 'error_message', 'created_at', 'updated_at',
    ] as $column) {
        expect(Schema::hasColumn('outbound_emails', $column))->toBeTrue("missing column {$column}");
    }
});

it('migration: outbound_emails down() drops the table, up() recreates it (AC-001)', function () {
    $migration = require database_path('migrations/2026_09_28_150200_create_outbound_emails_table.php');

    $migration->down();
    expect(Schema::hasTable('outbound_emails'))->toBeFalse();

    $migration->up();
    expect(Schema::hasTable('outbound_emails'))->toBeTrue();
});

it('model: OutboundEmail casts status to the enum, recipients to arrays, is a HasAttachments owner (AC-001)', function () {
    $workOrder = WorkOrder::factory()->create();
    $email = OutboundEmail::factory()->forEmailable($workOrder)->create([
        'status' => OutboundEmailStatus::Draft,
        'to_recipients' => ['a@example.com'],
    ]);
    $fresh = OutboundEmail::query()->findOrFail($email->id);

    expect($fresh->status)->toBe(OutboundEmailStatus::Draft)
        ->and($fresh->to_recipients)->toBe(['a@example.com'])
        ->and($fresh->getMorphClass())->toBe('outbound_email')
        ->and($fresh->emailable->is($workOrder))->toBeTrue();

    expect($workOrder->outboundEmails()->first()->id)->toBe($email->id);
});
