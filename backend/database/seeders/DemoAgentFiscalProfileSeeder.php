<?php

namespace Database\Seeders;

use App\Enums\CommissionRecipientRole;
use App\Enums\EnasarcoMandateType;
use App\Models\AgentFiscalProfile;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo fiscal profiles of the agents (spec 0212): one current profile for each
 * referent that earns a commercial, reporter or supervisor commission, mostly
 * withholding 23% on 50%, a couple with ENASARCO, and one agent with a closed
 * past profile followed by the current one (ranges never overlap). Depends on
 * DemoQuoteSeeder (the commission recipients). Re-runnable: looked up by
 * [referent_id, valid_from].
 */
class DemoAgentFiscalProfileSeeder extends Seeder
{
    private const int MAX_AGENTS = 10;

    private const string WITHHOLDING_RATE = '23.00';

    private const string WITHHOLDING_BASE_PERCENT = '50.00';

    /** Agents (by position) that carry ENASARCO instead of the plain withholding setup. */
    private const array ENASARCO_BY_POSITION = [
        1 => EnasarcoMandateType::Pluri,
        3 => EnasarcoMandateType::Mono,
    ];

    /** Position of the agent that also has a closed past profile. */
    private const int HISTORY_POSITION = 2;

    public function run(): void
    {
        // Step 1: the distinct agents that receive an agent-type commission
        $referentIds = DB::table('quote_line_commissions')
            ->where('recipient_type', 'referent')
            ->whereIn('recipient_role', [
                CommissionRecipientRole::Commercial->value,
                CommissionRecipientRole::Reporter->value,
                CommissionRecipientRole::Supervisor->value,
            ])
            ->distinct()->orderBy('recipient_id')->limit(self::MAX_AGENTS)->pluck('recipient_id');

        $current = CarbonImmutable::today()->startOfYear()->subYear();

        // Step 2: one current profile each, plus a past one for the history agent
        foreach ($referentIds->values() as $position => $referentId) {
            if ($position === self::HISTORY_POSITION) {
                $this->profile($referentId, $current->subYears(2), [
                    'valid_until' => $current->subDay()->toDateString(),
                    'notes' => 'Profilo precedente (demo).',
                ]);
            }

            $mandate = self::ENASARCO_BY_POSITION[$position] ?? null;

            $this->profile($referentId, $current, [
                'enasarco_enabled' => $mandate !== null,
                'enasarco_mandate_type' => $mandate?->value,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function profile(int $referentId, CarbonImmutable $validFrom, array $attributes): void
    {
        // whereDate, not an equality on the cast: SQLite stores the date cast as a datetime
        $profile = AgentFiscalProfile::query()
            ->where('referent_id', $referentId)
            ->whereDate('valid_from', $validFrom->toDateString())
            ->first() ?? new AgentFiscalProfile(['referent_id' => $referentId, 'valid_from' => $validFrom->toDateString()]);

        $profile->fill($attributes + [
            'withholding_enabled' => true,
            'withholding_rate' => self::WITHHOLDING_RATE,
            'withholding_base_percent' => self::WITHHOLDING_BASE_PERCENT,
            'enasarco_enabled' => false,
            'enasarco_mandate_type' => null,
            'valid_until' => null,
        ])->save();
    }
}
