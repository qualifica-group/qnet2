<?php

namespace Database\Seeders;

use App\Enums\ReportStatusColumn;
use App\Enums\WorkflowStatusGroup;
use App\Enums\WorkflowStatusSystemKey;
use App\Models\QuoteWorkflowStatus;
use App\Models\ReportColumnStatus;
use App\Services\RequestManagement\Report\ReportColumnStatusMap;
use Illuminate\Database\Seeder;

/**
 * One-shot for spec 0391:
 * `php artisan db:seed --class=ReportColumnStatusesSeeder --force`, after
 * `migrate`. Until it runs, the five status-based statistics columns are 0.
 *
 * Writes the first configuration of the statistics columns: today's counts,
 * except the rules Rosa Falzarano asked for (tasks 427235, 427248, 427656).
 * Does nothing when a configuration already exists, so a re-run never
 * overwrites what was saved from the statistics page.
 */
class ReportColumnStatusesSeeder extends Seeder
{
    private const string NEW_CONTACT_STATUS = 'nuovo contatto';

    private const string IN_NEGOTIATION_STATUS = 'in trattativa';

    /** Task 427235: these workflows count only "In trattativa" as potentials. */
    private const array POTENTIALS_IN_NEGOTIATION_ONLY = [
        'apl', 'finanza agevolata', 'gare', 'gdpr', 'iso', 'istruzione', 'sicurezza', 'soa',
    ];

    /** Task 427248: this workflow counts only "Da convocare" in "Invio presa in carico". */
    private const string GUIDANCE_WORKFLOW = 'orientamento specialistico';

    private const string INTAKE_STATUS = 'da convocare';

    private const array POTENTIAL_GROUPS = [WorkflowStatusGroup::Pending, WorkflowStatusGroup::Validated];

    public function run(ReportColumnStatusMap $map): void
    {
        if (ReportColumnStatus::query()->exists()) {
            $this->command?->info('Statistics columns already configured: nothing changed.');

            return;
        }

        $chosen = array_fill_keys(ReportStatusColumn::values(), []);
        $statuses = QuoteWorkflowStatus::query()->with('workflow:id,name')->get();

        foreach ($statuses as $status) {
            foreach (ReportStatusColumn::cases() as $column) {
                if ($this->counts($column, $status)) {
                    $chosen[$column->value][] = $status->id;
                }
            }
        }

        $map->replace($chosen);

        foreach ($chosen as $column => $ids) {
            $this->command?->line(sprintf('  %s: %d statuses', $column, count($ids)));
        }
    }

    private function counts(ReportStatusColumn $column, QuoteWorkflowStatus $status): bool
    {
        $name = $this->normalized($status->name);
        $workflow = $status->workflow === null ? null : $this->normalized($status->workflow->name);

        return match ($column) {
            // Task 427656: only "Nuovo Contatto"; the default set has none, its first status stands for it.
            ReportStatusColumn::NewContacts => $name === self::NEW_CONTACT_STATUS
                || ($workflow === null && $status->system_key === WorkflowStatusSystemKey::Open->value),
            ReportStatusColumn::Potentials => in_array($workflow, self::POTENTIALS_IN_NEGOTIATION_ONLY, true)
                ? $name === self::IN_NEGOTIATION_STATUS
                : in_array($status->group, self::POTENTIAL_GROUPS, true),
            ReportStatusColumn::IntakeSent => $workflow === self::GUIDANCE_WORKFLOW
                ? $name === self::INTAKE_STATUS
                : $status->group === WorkflowStatusGroup::ClosedWon,
            ReportStatusColumn::Associated, ReportStatusColumn::ConcludedDeals => $status->group === WorkflowStatusGroup::ClosedWon,
        };
    }

    private function normalized(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
