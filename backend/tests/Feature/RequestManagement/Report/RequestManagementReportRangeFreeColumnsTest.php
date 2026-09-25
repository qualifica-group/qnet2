<?php

declare(strict_types=1);

use App\Enums\WorkflowStatusGroup;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use App\Services\RequestManagement\Report\ReportDateRange;
use App\Services\RequestManagement\Report\ReportIndicatorRegistry;
use App\Services\RequestManagement\Report\ReportOperatorFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0159: the range-free columns (unhandled_callbacks,
 * unhandled_new_contacts, current_potentials) and `richiami`, now bound to
 * the picked range. Range used throughout: 2026-09-01 .. 2026-09-30, today
 * 2026-09-18.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-09-18 10:00:00');
    $this->category = ProductCategory::factory()->reportable()->create(['name' => 'GOL']);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

if (! function_exists('rangeFreeQuote')) {
    /**
     * @param  array<string, mixed>  $attributes
     */
    function rangeFreeQuote(ProductCategory $category, array $attributes = []): Quote
    {
        $opportunity = Opportunity::factory()->create();
        OpportunityProductLine::factory()->create([
            'opportunity_id' => $opportunity->id,
            'business_function_id' => BusinessFunction::factory()->create()->id,
            'product_category_id' => $category->id,
        ]);

        $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
        $quote->forceFill($attributes)->save();

        return $quote;
    }
}

if (! function_exists('rangeFreeTotal')) {
    function rangeFreeTotal(string $column, ProductCategory $category): int
    {
        Permission::findOrCreate('request-management.viewAll');
        $actor = User::factory()->create();
        $actor->givePermissionTo('request-management.viewAll');

        return app(ReportIndicatorRegistry::class)->resolve($column)
            ->compute([$category->id], $actor, ReportDateRange::fromRequest('2026-09-01', '2026-09-30'), ReportOperatorFilter::all())
            ->total;
    }
}

if (! function_exists('rangeFreeStatus')) {
    function rangeFreeStatus(WorkflowStatusGroup $group): int
    {
        return QuoteWorkflowStatus::factory()->global()->create(['group' => $group])->id;
    }
}

it('AC-001: the three range-free columns follow telefonate, ahead of their range-bound twins', function (): void {
    expect(array_slice((array) config('request-management-report.indicator_columns'), 0, 7))->toBe([
        'telefonate', 'unhandled_callbacks', 'unhandled_new_contacts', 'current_potentials',
        'richiami', 'nuovi_contatti', 'potenziali',
    ]);
});

it('AC-002: unhandled_callbacks counts open callbacks due up to today, whatever the range', function (): void {
    rangeFreeQuote($this->category, ['next_callback_at' => Carbon::parse('2026-08-01')]); // before the range
    rangeFreeQuote($this->category, ['next_callback_at' => Carbon::parse('2026-09-18 23:00:00')]);
    rangeFreeQuote($this->category, ['next_callback_at' => Carbon::parse('2026-09-25')]); // future: excluded
    rangeFreeQuote($this->category, [
        'next_callback_at' => Carbon::parse('2026-09-10'),
        'quote_workflow_status_id' => rangeFreeStatus(WorkflowStatusGroup::ClosedWon),
    ]);

    expect(rangeFreeTotal('unhandled_callbacks', $this->category))->toBe(2);
});

it('AC-003: richiami counts open callbacks due within the range only, future days included', function (): void {
    rangeFreeQuote($this->category, ['next_callback_at' => Carbon::parse('2026-09-01 00:00:00')]);
    rangeFreeQuote($this->category, ['next_callback_at' => Carbon::parse('2026-09-30 23:30:00')]); // future, in range
    rangeFreeQuote($this->category, ['next_callback_at' => Carbon::parse('2026-08-31 23:59:00')]); // before
    rangeFreeQuote($this->category, ['next_callback_at' => Carbon::parse('2026-10-01 00:00:00')]); // after
    rangeFreeQuote($this->category, [
        'next_callback_at' => Carbon::parse('2026-09-10'),
        'quote_workflow_status_id' => rangeFreeStatus(WorkflowStatusGroup::ClosedLost),
    ]);

    expect(rangeFreeTotal('richiami', $this->category))->toBe(2);
});

it('AC-004: unhandled_new_contacts counts first-state requests created at any time', function (): void {
    rangeFreeQuote($this->category, ['created_at' => Carbon::parse('2026-01-15')]);
    rangeFreeQuote($this->category, ['created_at' => Carbon::parse('2026-09-10')]);
    rangeFreeQuote($this->category, ['quote_workflow_status_id' => rangeFreeStatus(WorkflowStatusGroup::Pending)]);

    expect(rangeFreeTotal('unhandled_new_contacts', $this->category))->toBe(2)
        ->and(rangeFreeTotal('nuovi_contatti', $this->category))->toBe(1);
});

it('AC-005: current_potentials counts requests currently pending or validated, with no transition needed', function (): void {
    rangeFreeQuote($this->category, ['quote_workflow_status_id' => rangeFreeStatus(WorkflowStatusGroup::Pending)]);
    rangeFreeQuote($this->category, ['quote_workflow_status_id' => rangeFreeStatus(WorkflowStatusGroup::Validated)]);
    rangeFreeQuote($this->category, ['quote_workflow_status_id' => rangeFreeStatus(WorkflowStatusGroup::ClosedWon)]);
    rangeFreeQuote($this->category);

    expect(rangeFreeTotal('current_potentials', $this->category))->toBe(2)
        ->and(rangeFreeTotal('potenziali', $this->category))->toBe(0); // no logged transition in range
});

it('AC-006: only the three range-bound twins carry the period suffix, range-free ones keep the original names', function (): void {
    app()->setLocale('it');

    expect(__('request-management-report.headers.richiami'))->toBe('N. Richiami non gestiti (nel periodo selezionato)')
        ->and(__('request-management-report.headers.nuovi_contatti'))->toBe('N. Nuovi contatti non gestiti (nel periodo selezionato)')
        ->and(__('request-management-report.headers.potenziali'))->toBe('N. Potenziali associati (nel periodo selezionato)')
        ->and(__('request-management-report.headers.telefonate'))->toBe('N. Telefonate Effettuate')
        ->and(__('request-management-report.headers.associati'))->toBe('Associati')
        ->and(__('request-management-report.headers.unhandled_callbacks'))->toBe('N. Richiami non gestiti')
        ->and(__('request-management-report.headers.unhandled_new_contacts'))->toBe('N. Nuovi contatti non gestiti')
        ->and(__('request-management-report.headers.current_potentials'))->toBe('N. Potenziali associati');
});
