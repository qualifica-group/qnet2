<?php

use App\Enums\DocumentChecklistSyncStatus;
use App\Models\ArchivioSmartSetting;
use App\Models\DocumentChecklist;
use App\Models\DocumentChecklistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('returns items ordered by position', function () {
    $checklist = DocumentChecklist::factory()->create();
    DocumentChecklistItem::factory()->for($checklist, 'checklist')->create(['name' => 'B', 'position' => 1]);
    DocumentChecklistItem::factory()->for($checklist, 'checklist')->create(['name' => 'A', 'position' => 0]);
    DocumentChecklistItem::factory()->for($checklist, 'checklist')->create(['name' => 'C', 'position' => 2]);

    expect($checklist->items->pluck('name')->all())->toBe(['A', 'B', 'C'])
        ->and($checklist->items->first()->checklist->is($checklist))->toBeTrue()
        ->and($checklist->items->first()->allowed_file_types)->toBe(['pdf']);
});

it('soft deletes a checklist and keeps the row', function () {
    $checklist = DocumentChecklist::factory()->create();

    $checklist->delete();

    expect(DocumentChecklist::query()->find($checklist->id))->toBeNull()
        ->and(DocumentChecklist::withTrashed()->find($checklist->id)?->deleted_at)->not->toBeNull();
});

it('defaults a new checklist to pending and unlocked code', function () {
    $checklist = DocumentChecklist::factory()->create()->fresh();

    expect($checklist->sync_status)->toBe(DocumentChecklistSyncStatus::Pending)
        ->and($checklist->is_active)->toBeTrue()
        ->and($checklist->isCodeLocked())->toBeFalse();

    $checklist->update(['first_synced_at' => now()]);

    expect($checklist->isCodeLocked())->toBeTrue();
});

it('stores the sync logs with created_at only', function () {
    $checklist = DocumentChecklist::factory()->create();

    $log = $checklist->syncLogs()->create([
        'operation' => 'upsert', 'outcome' => 'success', 'http_status' => 200, 'attempt' => 1,
    ]);

    expect($log->created_at)->not->toBeNull()
        ->and($log->fresh()->operation->value)->toBe('upsert')
        ->and($checklist->syncLogs()->count())->toBe(1);
});

it('encrypts the ArchivioSmart token at rest and hides it', function () {
    $setting = ArchivioSmartSetting::factory()->create();

    $raw = DB::table('archivio_smart_settings')->where('id', $setting->id)->value('token');

    expect($raw)->not->toBe('as_test_token_123456')
        ->and($setting->fresh()->token)->toBe('as_test_token_123456')
        ->and($setting->toArray())->not->toHaveKey('token');
});

it('reports whether the connection is configured', function () {
    expect(ArchivioSmartSetting::current())->toBeNull();

    $setting = ArchivioSmartSetting::factory()->create();
    expect(ArchivioSmartSetting::current()?->is($setting))->toBeTrue()
        ->and($setting->isConfigured())->toBeTrue();

    $setting->update(['token' => null]);
    expect($setting->fresh()->isConfigured())->toBeFalse();

    $setting->update(['token' => 'as_test_token_123456', 'base_url' => '']);
    expect($setting->fresh()->isConfigured())->toBeFalse();
});
