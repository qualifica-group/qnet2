<?php

namespace Database\Seeders;

use App\Enums\WorkflowStatusGroup;
use App\Enums\WorkflowStatusSystemKey;
use App\Models\Opportunity;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteWorkflow;
use App\Models\QuoteWorkflowStatus;
use Database\Seeders\QualificaCatalog\AplOrientationRequestCatalogue;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/**
 * One-shot repair (user directive 2026-11-09): the Orientamento specialistico
 * workflow deleted that day comes back, replacing the one created in its place,
 * and its requests return to the status they had before the deletion reset them.
 *
 * The workflow and its statuses keep their original ids, as in the backup of
 * 2026-11-08, so the activity log entries written before the deletion resolve
 * again. A second run writes nothing.
 * Run with `php artisan db:seed --class=QualificaAplOrientationWorkflowRestoreSeeder`.
 */
class QualificaAplOrientationWorkflowRestoreSeeder extends Seeder
{
    private const int WORKFLOW_ID = 20;

    private const int OPEN_STATUS_ID = 354;

    private const string CRITERION_FIELD = 'product_category_id';

    private const string STATUS_PROPERTY = 'quote_workflow_status_id';

    private const string RESTORE_DESCRIPTION = 'Quote working status restored';

    private const string RELEASED_NAME_SUFFIX = ' (replaced)';

    /**
     * Where a status of the replacement workflow lands when the original has an
     * equivalent. Any other one falls back on the request's last original status.
     *
     * @var array<string, int>
     */
    private const array REPLACEMENT_EQUIVALENTS = [
        'Da Richiamare' => self::OPEN_STATUS_ID,
        'Appuntamento Fissato' => 357,
        'Irreperibile' => 361,
        'Non pertinente' => 362,
        'Numero inesistente' => 363,
        'Persa' => 370,
        'VINTO' => 371,
    ];

    /**
     * The original status set, from the backup of 2026-11-08.
     *
     * @var list<array{id: int, name: string, color: string, description: string, sort_order: int, system_key: ?string, group: WorkflowStatusGroup}>
     */
    private const array STATUSES = [
        ['id' => 354, 'name' => 'Da Richiamare', 'color' => 'slate', 'description' => 'Contatto da ricontattare per completare la lavorazione o fornire ulteriori informazioni.', 'sort_order' => 0, 'system_key' => 'open', 'group' => WorkflowStatusGroup::Open],
        ['id' => 355, 'name' => 'Attesa esito SFL/ADI', 'color' => 'slate', 'description' => "In attesa dell'esito relativo alla pratica SFL/ADI del candidato.", 'sort_order' => 10, 'system_key' => null, 'group' => WorkflowStatusGroup::Open],
        ['id' => 356, 'name' => 'Attesa _ App. CPI', 'color' => 'blue', 'description' => "In attesa della definizione dell'appuntamento presso il CPI.", 'sort_order' => 20, 'system_key' => null, 'group' => WorkflowStatusGroup::Pending],
        ['id' => 357, 'name' => 'OK App. Fissato CPI', 'color' => 'blue', 'description' => 'Appuntamento presso CPI fissato e confermato.', 'sort_order' => 30, 'system_key' => null, 'group' => WorkflowStatusGroup::Pending],
        ['id' => 358, 'name' => 'Attesa Documenti', 'color' => 'blue', 'description' => 'In attesa della ricezione della documentazione necessaria per procedere con la gestione della pratica.', 'sort_order' => 40, 'system_key' => null, 'group' => WorkflowStatusGroup::Pending],
        ['id' => 359, 'name' => 'NO _ Non ha Requisiti', 'color' => 'red', 'description' => 'Candidato non idoneo per mancanza dei requisiti previsti.', 'sort_order' => 50, 'system_key' => null, 'group' => WorkflowStatusGroup::ClosedLost],
        ['id' => 360, 'name' => 'Non interessato/a', 'color' => 'red', 'description' => 'Candidato che ha comunicato di non essere interessato al percorso.', 'sort_order' => 60, 'system_key' => null, 'group' => WorkflowStatusGroup::ClosedLost],
        ['id' => 361, 'name' => 'Irreperibile', 'color' => 'red', 'description' => 'Impossibile contattare il candidato dopo i tentativi effettuati.', 'sort_order' => 70, 'system_key' => null, 'group' => WorkflowStatusGroup::ClosedLost],
        ['id' => 362, 'name' => 'Non pertinente - Altra regione', 'color' => 'red', 'description' => "Candidato non pertinente perché appartenente a un'altra regione in cui non siamo accreditati.", 'sort_order' => 80, 'system_key' => null, 'group' => WorkflowStatusGroup::ClosedLost],
        ['id' => 363, 'name' => 'Numero Inesistente/Errato', 'color' => 'red', 'description' => 'Recapito telefonico non valido o inesistente.', 'sort_order' => 90, 'system_key' => null, 'group' => WorkflowStatusGroup::ClosedLost],
        ['id' => 364, 'name' => 'Doppione', 'color' => 'red', 'description' => 'Record duplicato presente nel sistema.', 'sort_order' => 100, 'system_key' => null, 'group' => WorkflowStatusGroup::ClosedLost],
        ['id' => 365, 'name' => 'Doppione già associato', 'color' => 'red', 'description' => "Record duplicato già collegato a un'associazione esistente.", 'sort_order' => 110, 'system_key' => null, 'group' => WorkflowStatusGroup::ClosedLost],
        ['id' => 366, 'name' => 'Da convocare', 'color' => 'emerald', 'description' => 'Contatto validato, da assegnare a un operatore APL per la convocazione.', 'sort_order' => 120, 'system_key' => null, 'group' => WorkflowStatusGroup::Validated],
        ['id' => 367, 'name' => 'Convocato', 'color' => 'blue', 'description' => 'Utente convocato, in attesa della presa in carico.', 'sort_order' => 130, 'system_key' => null, 'group' => WorkflowStatusGroup::Pending],
        ['id' => 368, 'name' => 'Presa in carico', 'color' => 'blue', 'description' => 'Utente preso in carico: politiche attive da erogare.', 'sort_order' => 140, 'system_key' => null, 'group' => WorkflowStatusGroup::Pending],
        ['id' => 369, 'name' => 'Monitoraggio SFL', 'color' => 'blue', 'description' => 'Politiche attive in corso: rinnovo SFL da monitorare mese per mese.', 'sort_order' => 150, 'system_key' => null, 'group' => WorkflowStatusGroup::Pending],
        ['id' => 370, 'name' => 'Perso', 'color' => 'red', 'description' => 'Pratica persa.', 'sort_order' => 160, 'system_key' => null, 'group' => WorkflowStatusGroup::ClosedLost],
        ['id' => 371, 'name' => 'Fine pratica', 'color' => 'green', 'description' => 'Politiche erogabili esaurite: pratica conclusa.', 'sort_order' => 170, 'system_key' => 'closed_won', 'group' => WorkflowStatusGroup::ClosedWon],
        ['id' => 372, 'name' => 'Associato NO _ Altro Ente', 'color' => 'red', 'description' => 'Candidato associato a un altro ente diverso da NOI.', 'sort_order' => 180, 'system_key' => 'closed_lost', 'group' => WorkflowStatusGroup::ClosedLost],
    ];

    public function run(): void
    {
        // Step 1: the category and the workflow standing in for the original.
        $category = ProductCategory::query()->where('name', AplOrientationRequestCatalogue::CATEGORY)->sole();
        $replacement = QuoteWorkflow::query()
            ->where('name', AplOrientationRequestCatalogue::CATEGORY)
            ->whereKeyNot(self::WORKFLOW_ID)
            ->first();

        $outcome = DB::transaction(function () use ($category, $replacement): array {
            // Step 2: name and criteria are unique, so the replacement frees them first.
            $replacementStatusIds = $this->releaseReplacement($replacement);

            // Step 3: the original workflow, under its own ids.
            $this->restoreWorkflow($category);

            // Step 4: each request back on its status.
            $restored = $this->restoreRequests($this->requestsToRestore($category, $replacementStatusIds));

            // Step 5: nothing points at the replacement any more.
            $replacement?->delete();

            return $restored;
        });

        // Step 6: the summary.
        $this->command?->info(sprintf('APL Orientamento specialistico: %d requests restored.', $outcome['restored']));

        if ($outcome['without_history'] !== []) {
            $this->command?->warn('No status in the log, placed on the initial status, offer id: '.implode(', ', $outcome['without_history']));
        }
    }

    /**
     * @return Collection<int, int> the ids of the replacement's statuses
     */
    private function releaseReplacement(?QuoteWorkflow $replacement): Collection
    {
        if ($replacement === null) {
            return collect();
        }

        QuoteWorkflow::query()->whereKey($replacement->id)->update([
            'name' => $replacement->name.self::RELEASED_NAME_SUFFIX,
            'criteria_signature' => null,
        ]);

        return $replacement->statuses()->pluck('id');
    }

    private function restoreWorkflow(ProductCategory $category): void
    {
        if (QuoteWorkflow::query()->whereKey(self::WORKFLOW_ID)->exists()) {
            return;
        }

        $workflow = new QuoteWorkflow;
        $workflow->forceFill([
            'id' => self::WORKFLOW_ID,
            'name' => AplOrientationRequestCatalogue::CATEGORY,
            'is_active' => true,
            'criteria_signature' => self::CRITERION_FIELD.':'.$category->id,
        ])->save();

        $workflow->criteria()->create(['field' => self::CRITERION_FIELD, 'value_id' => $category->id]);

        foreach (self::STATUSES as $status) {
            (new QuoteWorkflowStatus)->forceFill([...$status, 'quote_workflow_id' => self::WORKFLOW_ID, 'requires_note' => false])->save();
        }
    }

    /**
     * Requests on a status of the replacement, and those of the category the
     * deletion left on the initial status of some other workflow.
     *
     * @param  Collection<int, int>  $replacementStatusIds
     * @return EloquentCollection<int, Quote>
     */
    private function requestsToRestore(ProductCategory $category, Collection $replacementStatusIds): EloquentCollection
    {
        return Quote::query()
            ->where(fn ($requests) => $requests
                ->whereIn('quote_workflow_status_id', $replacementStatusIds)
                ->orWhere(fn ($reset) => $reset
                    ->whereHas('offerLines.product', fn ($product) => $product->where('category_id', $category->id))
                    ->whereHas('quoteWorkflowStatus', fn ($status) => $status
                        ->where('system_key', WorkflowStatusSystemKey::Open->value)
                        ->where('quote_workflow_id', '!=', self::WORKFLOW_ID))))
            ->with(['opportunity', 'quoteWorkflowStatus'])
            ->get();
    }

    /**
     * Written directly and with no author on purpose: the move restores a past
     * state, so the writer's note and resolved-set checks do not apply, and an
     * author would present it as that person's own choice.
     *
     * @param  EloquentCollection<int, Quote>  $requests
     * @return array{restored: int, without_history: list<int>}
     */
    private function restoreRequests(EloquentCollection $requests): array
    {
        $lastOriginalStatuses = $this->lastOriginalStatusIds($requests);
        $outcome = ['restored' => 0, 'without_history' => []];

        foreach ($requests as $quote) {
            $logged = $lastOriginalStatuses->get($quote->opportunity_id);
            $targetId = self::REPLACEMENT_EQUIVALENTS[$quote->quoteWorkflowStatus->name] ?? $logged ?? self::OPEN_STATUS_ID;

            if ($logged === null) {
                $outcome['without_history'][] = $quote->id;
            }

            $previousId = $quote->quote_workflow_status_id;
            $quote->quote_workflow_status_id = $targetId;
            $quote->save();

            activity($quote->opportunity->getTable())
                ->performedOn($quote->opportunity)
                ->event('updated')
                ->withProperties([
                    'attributes' => [self::STATUS_PROPERTY => $targetId],
                    'old' => [self::STATUS_PROPERTY => $previousId],
                ])
                ->log(self::RESTORE_DESCRIPTION);

            $outcome['restored']++;
        }

        return $outcome;
    }

    /**
     * @param  EloquentCollection<int, Quote>  $requests
     * @return Collection<int, int> last logged status of the original set, by opportunity id
     */
    private function lastOriginalStatusIds(EloquentCollection $requests): Collection
    {
        $originalIds = array_column(self::STATUSES, 'id');

        return Activity::query()
            ->where('subject_type', (new Opportunity)->getMorphClass())
            ->whereIn('subject_id', $requests->pluck('opportunity_id'))
            ->where('properties', 'like', '%'.self::STATUS_PROPERTY.'%')
            ->orderBy('id')
            ->get()
            ->filter(fn (Activity $entry): bool => in_array(data_get($entry->properties, 'attributes.'.self::STATUS_PROPERTY), $originalIds, true))
            ->mapWithKeys(fn (Activity $entry): array => [$entry->subject_id => data_get($entry->properties, 'attributes.'.self::STATUS_PROPERTY)]);
    }
}
