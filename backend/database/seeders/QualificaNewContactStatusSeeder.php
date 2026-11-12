<?php

namespace Database\Seeders;

use App\Enums\ReportStatusColumn;
use App\Enums\WorkflowStatusGroup;
use App\Enums\WorkflowStatusSystemKey;
use App\Models\QuoteWorkflow;
use App\Models\QuoteWorkflowStatus;
use App\Models\ReportColumnStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * One-shot (user directive 2026-11-12, spec 0391): every quote status set
 * starts with "Nuovo Contatto". Run BEFORE ReportColumnStatusesSeeder:
 * `php artisan db:seed --class=QualificaNewContactStatusSeeder --force`.
 *
 * A set that already has a "Nuovo Contatto" is left alone. Otherwise its
 * initial status (`system_key = open`):
 * - named "Aperta", the generic "not worked yet" status: renamed, so its
 *   requests keep being new contacts;
 * - with a meaning of its own ("Da Richiamare", "Attesa Abilitazione CPI"):
 *   becomes an ordinary status right after a new initial "Nuovo Contatto";
 *   its requests and their history stay where they are.
 *
 * When the statistics columns are already configured, the new status is
 * added to "Nuovi contatti non gestiti". A second run writes nothing.
 */
class QualificaNewContactStatusSeeder extends Seeder
{
    private const string NEW_CONTACT = 'Nuovo Contatto';

    private const string NEW_CONTACT_DESCRIPTION = 'Nuovo contatto acquisito, non ancora lavorato.';

    private const string NEW_CONTACT_COLOR = 'slate';

    private const string GENERIC_OPEN_NAME = 'aperta';

    private const int STEP = 10;

    public function run(): void
    {
        $workflowIds = [null, ...QuoteWorkflow::query()->orderBy('id')->pluck('id')->all()];

        foreach ($workflowIds as $workflowId) {
            $outcome = DB::transaction(fn (): ?string => $this->ensureNewContact($workflowId));

            if ($outcome !== null) {
                $this->command?->line(sprintf('  %s: %s', $workflowId === null ? 'Stati predefiniti' : "workflow {$workflowId}", $outcome));
            }
        }
    }

    /**
     * @return string|null what changed, null when nothing did
     */
    private function ensureNewContact(?int $workflowId): ?string
    {
        $statuses = QuoteWorkflowStatus::query()->where('quote_workflow_id', $workflowId)->get();

        if ($statuses->contains(fn (QuoteWorkflowStatus $status): bool => $this->normalized($status->name) === $this->normalized(self::NEW_CONTACT))) {
            return null;
        }

        $open = $statuses->firstWhere('system_key', WorkflowStatusSystemKey::Open->value);

        if ($open === null) {
            return null;
        }

        // Step 1: the generic initial status only changes its name.
        if ($this->normalized($open->name) === self::GENERIC_OPEN_NAME) {
            $open->forceFill(['name' => self::NEW_CONTACT, 'description' => $open->description ?? self::NEW_CONTACT_DESCRIPTION])->save();

            return "renamed \"{$open->getOriginal('name')}\"";
        }

        // Step 2: otherwise the old initial status becomes ordinary, a new one takes its place.
        $previousName = $open->name;
        $open->forceFill(['system_key' => null])->save();
        $created = QuoteWorkflowStatus::query()->forceCreate([
            'quote_workflow_id' => $workflowId,
            'name' => self::NEW_CONTACT,
            'description' => self::NEW_CONTACT_DESCRIPTION,
            'color' => self::NEW_CONTACT_COLOR,
            'sort_order' => 0,
            'system_key' => WorkflowStatusSystemKey::Open->value,
            'group' => WorkflowStatusGroup::Open,
            'requires_note' => false,
        ]);

        // Step 3: order: the new initial status, the former one, the rest, the closed tail.
        $this->resequence($workflowId);

        // Step 4: statistics already configured count it as a new contact.
        if (ReportColumnStatus::query()->exists()) {
            ReportColumnStatus::query()->firstOrCreate([
                'column_key' => ReportStatusColumn::NewContacts->value,
                'quote_workflow_status_id' => $created->id,
            ]);
        }

        return "added before \"{$previousName}\"";
    }

    private function resequence(?int $workflowId): void
    {
        $customIds = QuoteWorkflowStatus::query()
            ->where('quote_workflow_id', $workflowId)
            ->whereNull('system_key')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id');

        $sortOrder = self::STEP;
        foreach ($customIds as $id) {
            QuoteWorkflowStatus::query()->whereKey($id)->update(['sort_order' => $sortOrder]);
            $sortOrder += self::STEP;
        }

        foreach (WorkflowStatusSystemKey::tailKeys() as $key) {
            QuoteWorkflowStatus::query()
                ->where('quote_workflow_id', $workflowId)
                ->where('system_key', $key->value)
                ->update(['sort_order' => $sortOrder]);
            $sortOrder += self::STEP;
        }
    }

    private function normalized(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
