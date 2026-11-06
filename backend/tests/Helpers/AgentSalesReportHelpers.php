<?php

declare(strict_types=1);

use App\Enums\CommissionRecipientRole;
use App\Enums\QuoteLineType;
use App\Models\City;
use App\Models\CompanySite;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\QuoteLineCommission;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use Spatie\Permission\Models\Permission;

// Shared by the Report Vendite Agenti table and export tests (spec 0365).

if (! function_exists('agentSalesReportActor')) {
    /**
     * An actor allowed to open the report. With $seesAll it also holds `quotes.viewAll`
     * (spec 0316), without it only the offers it follows are visible.
     */
    function agentSalesReportActor(bool $seesAll = true): User
    {
        $user = User::factory()->create()->givePermissionTo(Permission::findOrCreate('agent-sales-report.view', 'web'));

        return $seesAll ? $user->givePermissionTo(Permission::findOrCreate('quotes.viewAll', 'web')) : $user;
    }
}

if (! function_exists('agentSalesReportRegistry')) {
    /**
     * A registry whose card carries a primary address (with city), a primary phone and
     * email, and decoys: a non-primary address and a non-primary phone.
     */
    function agentSalesReportRegistry(string $name, string $cityName = 'Milano'): Registry
    {
        $registry = Registry::factory()->create(['name' => $name]);
        $card = $registry->personalData()->create(['type' => 'company', 'company_name' => $name]);
        $city = City::query()->firstWhere('name', $cityName) ?? City::factory()->create(['name' => $cityName]);

        $card->addresses()->create(['line1' => 'Via Roma 1', 'line2' => 'Scala B', 'postal_code' => '20100', 'city_id' => $city->id, 'is_primary' => true]);
        $card->addresses()->create(['line1' => 'Via Decoy 9', 'postal_code' => '00100', 'is_primary' => false]);
        $card->contacts()->create(['type' => 'phone', 'value' => '+39 02 1234567', 'is_primary' => true]);
        $card->contacts()->create(['type' => 'phone', 'value' => '+39 02 0000000', 'is_primary' => false]);
        $card->contacts()->create(['type' => 'email', 'value' => $name === '' ? 'x@example.com' : strtolower(str_replace(' ', '', $name)).'@example.com', 'is_primary' => true]);

        return $registry;
    }
}

if (! function_exists('agentSalesReportQuote')) {
    /**
     * @param  array<string, mixed>  $attributes
     */
    function agentSalesReportQuote(?Registry $registry = null, array $attributes = []): Quote
    {
        $opportunity = Opportunity::factory()->create(['registry_id' => ($registry ?? agentSalesReportRegistry('Acme Spa'))->id]);

        return Quote::factory()->create(['opportunity_id' => $opportunity->id, 'quote_date' => '2026-03-10', ...$attributes]);
    }
}

if (! function_exists('agentSalesReportLine')) {
    /**
     * A line with its commissions per role, e.g. ['SUPERVISOR' => 10, 'COMMERCIAL' => 20].
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, float|int>  $commissions
     */
    function agentSalesReportLine(Quote $quote, array $attributes = [], array $commissions = []): QuoteLine
    {
        $line = QuoteLine::factory()->create(['quote_id' => $quote->id, 'line_type' => QuoteLineType::Revenue, ...$attributes]);

        foreach ($commissions as $role => $amount) {
            QuoteLineCommission::factory()->create([
                'quote_line_id' => $line->id,
                'recipient_role' => CommissionRecipientRole::from($role),
                'calculated_amount' => $amount,
            ]);
        }

        return $line;
    }
}

if (! function_exists('agentSalesReportTeam')) {
    /**
     * Gives the quote a company site, commercial, reporter and one account manager.
     *
     * @return array{commercial: Referent, reporter: Referent, manager: User}
     */
    function agentSalesReportTeam(Quote $quote): array
    {
        $commercial = Referent::factory()->create(['name' => 'Carla Commerciale']);
        $reporter = Referent::factory()->create(['name' => 'Sara Segnalatrice']);
        $manager = User::factory()->create(['name' => 'Gino Gestore']);

        $quote->forceFill([
            'commercial_id' => $commercial->id,
            'reporter_id' => $reporter->id,
            'company_site_id' => CompanySite::factory()->create(['name' => 'Sede Nord'])->id,
        ])->saveQuietly();
        $quote->managers()->attach($manager->id, ['position' => 2]);

        return compact('commercial', 'reporter', 'manager');
    }
}
