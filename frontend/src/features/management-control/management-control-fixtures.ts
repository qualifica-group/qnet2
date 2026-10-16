import type {
  FunctionRow,
  IncomeStatement,
  IncomeStatementFigures,
  IncomeStatementTotals,
  SegmentRow,
  StructureCosts,
} from '@/features/management-control/types'

export function makeFigures(overrides: Partial<IncomeStatementFigures> = {}): IncomeStatementFigures {
  return {
    revenue_contract: 1000,
    revenue_invoiced: 800,
    revenue_collected: 400,
    revenue_actual: 1000,
    cost_budget: 300,
    cost_manual: 100,
    cost_committed: 50,
    cost_invoiced: 120,
    cost_labor: 80,
    cost_actual: 350,
    commission_budget: 100,
    commission_actual: 90,
    margin_budget: 600,
    margin_actual: 560,
    cost_delta: 50,
    margin_delta: -40,
    labor_minutes: 240,
    unpriced_minutes: 0,
    ...overrides,
  }
}

export function makeTotals(overrides: Partial<IncomeStatementTotals> = {}): IncomeStatementTotals {
  return {
    work_orders_count: 3,
    ...makeFigures({ margin_actual: 660, margin_budget: 900, margin_delta: -240, cost_labor: 120, labor_minutes: 360, unpriced_minutes: 60 }),
    ...overrides,
  }
}

export function makeFunction(overrides: Partial<FunctionRow> = {}): FunctionRow {
  return { key: '3', label: 'Training', work_orders_count: 2, ...makeFigures(), ...overrides }
}

export function makeSegment(overrides: Partial<SegmentRow> = {}): SegmentRow {
  return {
    key: '1',
    label: 'Acme Spa',
    work_orders_count: 2,
    ...makeFigures(),
    by_business_function: [makeFunction()],
    ...overrides,
  }
}

export function makeIncomeStatement(overrides: Partial<IncomeStatement> = {}): IncomeStatement {
  return {
    applied: {
      date_from: '2026-01-01',
      date_to: '2026-06-30',
      company_id: [],
      company_site_id: [],
      operational_site_id: [],
      business_function_id: [],
      work_order_type: [],
      commercial_id: [],
      revenue_basis: 'contract',
      segment_by: 'none',
    },
    totals: makeTotals(),
    by_business_function: [makeFunction(), makeFunction({ key: 'unattributed', label: null, work_orders_count: 1, cost_labor: 40, labor_minutes: 120, unpriced_minutes: 60 })],
    segments: [],
    ...overrides,
  }
}

export function makeStructureCosts(overrides: Partial<StructureCosts> = {}): StructureCosts {
  return {
    applied: {
      date_from: '2026-01-01',
      date_to: '2026-06-30',
      company_id: [],
      company_site_id: [],
      operational_site_id: [],
      group_by: 'company',
    },
    totals: { committed_net: 100, invoiced_net: 250, total_net: 350 },
    by_cost_type: [
      {
        cost_type: { id: 1, name: 'Rent', color: '#336699', nature: 'general' },
        committed_net: 100,
        invoiced_net: 150,
        total_net: 250,
      },
      { cost_type: null, committed_net: 0, invoiced_net: 100, total_net: 100 },
    ],
    by_dimension: [
      { key: '1', label: 'Acme Spa', committed_net: 100, invoiced_net: 150, total_net: 250 },
      { key: 'unattributed', label: null, committed_net: 0, invoiced_net: 100, total_net: 100 },
    ],
    ...overrides,
  }
}
