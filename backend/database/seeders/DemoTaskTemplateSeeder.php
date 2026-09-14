<?php

namespace Database\Seeders;

use App\DataObjects\TaskTemplates\CreateTaskTemplateData;
use App\DataObjects\TaskTemplates\TaskTemplateItemData;
use App\Enums\TaskStatusGroup;
use App\Models\TaskStatus;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use App\Services\TaskTemplateService;
use Illuminate\Database\Seeder;

/**
 * Development seed for the "Modelli di Task" module (spec 0124): a handful
 * of realistic templates an admin might configure, so
 * WorkOrders\WorkOrderTaskGenerator has something to pick from when a demo
 * Commessa is created through the "Modello di Task" field. Written through
 * the REAL write path (TaskTemplateService::create()), the same convention
 * DemoTaskSeeder follows with TaskService::create() — the seed exercises
 * exactly what the module's own form would produce, including the full
 * `items` sync.
 *
 * `task_status_id` is resolved by PHASE (TaskStatusGroup::Open/Pending, D-4),
 * never a hard-coded id: QualificaTaskTaxonomySeeder (which DemoDataSeeder
 * runs immediately before this step) guarantees at least one active status
 * in each of those two phases, but this seeder never assumes WHICH row wins
 * — most rows are deliberately left with NO status (D-4's own "unset" case,
 * resolved by TaskInitialStatusResolver at generation time). No attachments
 * are seeded here (out of scope).
 *
 * Idempotent: an existing template of the SAME name — never referenced by a
 * Commessa at this point in the seed, since nothing calls
 * WorkOrderTaskGenerator during seeding — is deleted (its rows too, through
 * Eloquent's own `::delete()`, mirroring TaskTemplateService::delete()) and
 * recreated, so a re-run reproduces the exact same dataset rather than
 * piling items on top of it.
 */
class DemoTaskTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(TaskTemplateService::class);
        $openStatusId = TaskStatus::query()->where('group', TaskStatusGroup::Open)->where('is_active', true)->value('id');
        $pendingStatusId = TaskStatus::query()->where('group', TaskStatusGroup::Pending)->where('is_active', true)->value('id');

        foreach ($this->catalogue($openStatusId, $pendingStatusId) as [$name, $description, $items]) {
            $this->clearExisting($name);

            $service->create(new CreateTaskTemplateData(
                name: $name,
                description: $description,
                isActive: true,
                items: $items,
            ));
        }
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: array<int, TaskTemplateItemData>}>
     */
    private function catalogue(?int $openStatusId, ?int $pendingStatusId): array
    {
        return [
            [
                'Avvio commessa standard',
                'Attivita di apertura comuni a ogni nuova commessa: kickoff interno, sopralluogo preliminare e raccolta della documentazione.',
                [
                    new TaskTemplateItemData(null, 'Kickoff interno', 'Allineamento tra i supervisori assegnati sugli obiettivi della commessa.', 30, $openStatusId, 1),
                    new TaskTemplateItemData(null, 'Sopralluogo preliminare', 'Prima visita presso il cliente per verificare lo stato dei luoghi.', 120, null, 3),
                    new TaskTemplateItemData(null, 'Raccolta documentazione cliente', 'Richiesta e raccolta dei documenti necessari allavvio dei lavori.', 60, $pendingStatusId, 5),
                    new TaskTemplateItemData(null, 'Pianificazione risorse', 'Definizione del team e delle tempistiche operative.', 45, null, 7),
                ],
            ],
            [
                'Sopralluogo e progettazione',
                'Percorso tecnico dal sopralluogo alla progettazione preliminare, fino allapprovazione del cliente.',
                [
                    new TaskTemplateItemData(null, 'Sopralluogo tecnico', 'Rilievo dello stato di fatto e delle criticita presenti.', 180, $openStatusId, 2),
                    new TaskTemplateItemData(null, 'Rilievo misure', 'Misurazioni di dettaglio degli ambienti coinvolti.', 90, null, 3),
                    new TaskTemplateItemData(null, 'Stesura progetto preliminare', 'Redazione della proposta tecnica preliminare.', 240, null, 10),
                    new TaskTemplateItemData(null, 'Revisione interna progetto', 'Verifica tecnica del progetto prima dellinvio al cliente.', 60, $pendingStatusId, 12),
                    new TaskTemplateItemData(null, 'Approvazione cliente', 'Presentazione del progetto e raccolta del via libera del cliente.', 30, null, 15),
                ],
            ],
            [
                'Chiusura e collaudo',
                'Attivita di chiusura commessa: collaudo finale, verbale di consegna e follow-up post-consegna.',
                [
                    new TaskTemplateItemData(null, 'Collaudo finale', 'Verifica finale della conformita dei lavori eseguiti.', 90, $openStatusId, 1),
                    new TaskTemplateItemData(null, 'Verbale di consegna', 'Redazione e firma del verbale di consegna con il cliente.', 30, null, 2),
                    new TaskTemplateItemData(null, 'Follow-up post-consegna', 'Contatto di cortesia a distanza di due settimane dalla consegna.', 20, $pendingStatusId, 14),
                ],
            ],
        ];
    }

    private function clearExisting(string $name): void
    {
        $template = TaskTemplate::query()->where('name', $name)->first();

        if ($template === null) {
            return;
        }

        $template->items()->get()->each(static fn (TaskTemplateItem $item) => $item->delete());
        $template->delete();
    }
}
