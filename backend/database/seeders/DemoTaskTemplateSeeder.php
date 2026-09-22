<?php

namespace Database\Seeders;

use App\DataObjects\TaskTemplates\CreateTaskTemplateData;
use App\DataObjects\TaskTemplates\TaskTemplateItemData;
use App\DataObjects\TaskTemplates\TaskTemplateStageData;
use App\Enums\TaskStatusGroup;
use App\Models\TaskStatus;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use App\Models\User;
use App\Services\TaskTemplateService;
use Database\Seeders\Concerns\SeedsDevelopmentUsers;
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
 * piling items on top of it. Its `task_template_stages` rows cascade off the
 * plain `$template->delete()` at the DB level (spec 0146, D-2).
 *
 * Spec 0146, D-2: two of the three templates carry a couple of "Fasi" each,
 * the third mixes a staged row with an unstaged one — so
 * WorkOrderTaskGenerator::copyStages() and the "Senza fase" path both have a
 * realistic fixture to generate a commessa from.
 */
class DemoTaskTemplateSeeder extends Seeder
{
    use SeedsDevelopmentUsers;

    public function run(): void
    {
        $service = app(TaskTemplateService::class);
        // Spec 0128: TaskTemplateService::create() now takes the actor it
        // writes rich text attachments as — no template here embeds an
        // image, but the demo account (DemoUsersSeeder already ran) is the
        // same "acting user" convention DemoTimeEntrySeeder's pickUsers()
        // already follows.
        $actor = User::query()->where('email', self::DEMO_EMAIL)->firstOrFail();
        $openStatusId = TaskStatus::query()->where('group', TaskStatusGroup::Open)->where('is_active', true)->value('id');
        $pendingStatusId = TaskStatus::query()->where('group', TaskStatusGroup::Pending)->where('is_active', true)->value('id');

        foreach ($this->catalogue($openStatusId, $pendingStatusId) as [$name, $description, $stages, $items]) {
            $this->clearExisting($name);

            $service->create(new CreateTaskTemplateData(
                name: $name,
                description: $description,
                isActive: true,
                items: $items,
                stages: $stages,
            ), $actor);
        }
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: array<int, TaskTemplateStageData>, 3: array<int, TaskTemplateItemData>}>
     */
    private function catalogue(?int $openStatusId, ?int $pendingStatusId): array
    {
        return [
            [
                'Avvio commessa standard',
                'Attivita di apertura comuni a ogni nuova commessa: kickoff interno, sopralluogo preliminare e raccolta della documentazione.',
                [
                    new TaskTemplateStageData(null, 'preparazione', 'Preparazione'),
                    new TaskTemplateStageData(null, 'organizzazione', 'Organizzazione'),
                ],
                [
                    new TaskTemplateItemData(null, 'Kickoff interno', 'Allineamento tra i supervisori assegnati sugli obiettivi della commessa.', 30, $openStatusId, 1, 'preparazione'),
                    new TaskTemplateItemData(null, 'Sopralluogo preliminare', 'Prima visita presso il cliente per verificare lo stato dei luoghi.', 120, null, 3, 'preparazione'),
                    new TaskTemplateItemData(null, 'Raccolta documentazione cliente', 'Richiesta e raccolta dei documenti necessari allavvio dei lavori.', 60, $pendingStatusId, 5, 'organizzazione'),
                    new TaskTemplateItemData(null, 'Pianificazione risorse', 'Definizione del team e delle tempistiche operative.', 45, null, 7, 'organizzazione'),
                ],
            ],
            [
                'Sopralluogo e progettazione',
                'Percorso tecnico dal sopralluogo alla progettazione preliminare, fino allapprovazione del cliente.',
                [
                    new TaskTemplateStageData(null, 'sopralluogo', 'Sopralluogo'),
                    new TaskTemplateStageData(null, 'progettazione', 'Progettazione'),
                ],
                [
                    new TaskTemplateItemData(null, 'Sopralluogo tecnico', 'Rilievo dello stato di fatto e delle criticita presenti.', 180, $openStatusId, 2, 'sopralluogo'),
                    new TaskTemplateItemData(null, 'Rilievo misure', 'Misurazioni di dettaglio degli ambienti coinvolti.', 90, null, 3, 'sopralluogo'),
                    new TaskTemplateItemData(null, 'Stesura progetto preliminare', 'Redazione della proposta tecnica preliminare.', 240, null, 10, 'progettazione'),
                    new TaskTemplateItemData(null, 'Revisione interna progetto', 'Verifica tecnica del progetto prima dellinvio al cliente.', 60, $pendingStatusId, 12, 'progettazione'),
                    new TaskTemplateItemData(null, 'Approvazione cliente', 'Presentazione del progetto e raccolta del via libera del cliente.', 30, null, 15, 'progettazione'),
                ],
            ],
            [
                'Chiusura e collaudo',
                'Attivita di chiusura commessa: collaudo finale, verbale di consegna e follow-up post-consegna.',
                [
                    new TaskTemplateStageData(null, 'chiusura', 'Chiusura'),
                ],
                [
                    new TaskTemplateItemData(null, 'Collaudo finale', 'Verifica finale della conformita dei lavori eseguiti.', 90, $openStatusId, 1, 'chiusura'),
                    new TaskTemplateItemData(null, 'Verbale di consegna', 'Redazione e firma del verbale di consegna con il cliente.', 30, null, 2, 'chiusura'),
                    // Deliberately left in "Senza fase" (no stage_key): the
                    // seed exercises both the staged and the unstaged path.
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
