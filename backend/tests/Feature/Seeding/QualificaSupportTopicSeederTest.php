<?php

use App\Models\SupportTopic;
use App\Models\TaskPriority;
use App\Models\User;
use Database\Seeders\QualificaCatalog\SupportTopicCatalogue;
use Database\Seeders\QualificaSupportTopicSeeder;
use Database\Seeders\QualificaTaskTaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates every IT support type assigned to the IT contact, with the catalogue Task values', function () {
    $this->seed(QualificaTaskTaxonomySeeder::class);
    $assignee = User::factory()->create(['email' => SupportTopicCatalogue::ASSIGNEE_EMAIL]);

    $this->seed(QualificaSupportTopicSeeder::class);

    $names = array_column(SupportTopicCatalogue::TOPICS, 0);
    $topics = SupportTopic::query()->with(['assignees', 'taskType', 'taskCategory', 'taskPriority', 'urgentTaskPriority', 'taskImportance'])
        ->orderBy('sort_order')->get();

    expect($topics->pluck('name')->all())->toBe($names)
        ->and(SupportTopic::query()->available()->count())->toBe(count($names));

    $topics->each(function (SupportTopic $topic) use ($assignee): void {
        expect($topic->assignees->pluck('id')->all())->toBe([$assignee->id])
            ->and($topic->taskType?->name)->toBe(SupportTopicCatalogue::TASK_TYPE)
            ->and($topic->taskCategory?->name)->toBe(SupportTopicCatalogue::TASK_CATEGORY)
            ->and($topic->taskPriority?->name)->toBe(SupportTopicCatalogue::TASK_PRIORITY)
            ->and($topic->urgentTaskPriority?->name)->toBe(SupportTopicCatalogue::URGENT_TASK_PRIORITY)
            ->and($topic->taskImportance?->name)->toBe(SupportTopicCatalogue::TASK_IMPORTANCE)
            ->and($topic->due_days)->toBe(SupportTopicCatalogue::DUE_DAYS)
            ->and($topic->urgent_due_days)->toBe(SupportTopicCatalogue::URGENT_DUE_DAYS);
    });
});

it('leaves an existing type alone on a re-run and never duplicates', function () {
    $this->seed(QualificaTaskTaxonomySeeder::class);
    User::factory()->create(['email' => SupportTopicCatalogue::ASSIGNEE_EMAIL]);
    $this->seed(QualificaSupportTopicSeeder::class);

    $edited = SupportTopic::query()->where('name', 'Stampante')->sole();
    $edited->update(['description' => 'Testo cambiato da Configurazione', 'task_priority_id' => TaskPriority::query()->where('name', 'Alta')->value('id')]);

    $this->seed(QualificaSupportTopicSeeder::class);

    expect(SupportTopic::query()->count())->toBe(count(SupportTopicCatalogue::TOPICS))
        ->and($edited->fresh()->description)->toBe('Testo cambiato da Configurazione')
        ->and($edited->fresh()->taskPriority->name)->toBe('Alta');
});

it('creates nothing when the IT contact has no account', function () {
    $this->seed(QualificaTaskTaxonomySeeder::class);

    $this->seed(QualificaSupportTopicSeeder::class);

    expect(SupportTopic::query()->count())->toBe(0);
});
