import type { Ledger, LedgerDocument, LedgerInstallment, LedgerSection } from '@/features/ledger/types'

const EMPTY_AGING = {
  overdue_1_30: 0,
  overdue_31_60: 0,
  overdue_61_90: 0,
  overdue_over_90: 0,
  due_0_30: 0,
  due_31_60: 0,
  due_over_60: 0,
}

export function makeInstallment(overrides: Partial<LedgerInstallment> = {}): LedgerInstallment {
  return {
    id: 1,
    sequence: 1,
    due_date: '2026-03-31',
    amount: 500,
    settled_amount: null,
    settled_at: null,
    residual_amount: 500,
    days_late: 12,
    ...overrides,
  }
}

export function makeDocument(overrides: Partial<LedgerDocument> = {}): LedgerDocument {
  return {
    id: 10,
    document_type: 'invoice',
    number_label: '12/2026',
    external_number: null,
    document_date: '2026-03-01',
    company: { id: 1, name: 'Acme' },
    company_site: null,
    operational_site: null,
    debit: 500,
    credit: 0,
    settled_amount: 0,
    residual_amount: 500,
    running_balance: 500,
    status: 'overdue',
    installments: [makeInstallment()],
    ...overrides,
  }
}

export function makeSection(overrides: Partial<LedgerSection> = {}): LedgerSection {
  return {
    opening_balance: 200,
    summary: {
      invoiced_total: 500,
      settled_total: 0,
      residual_total: 700,
      overdue_total: 500,
      not_due_total: 0,
      aging: { ...EMPTY_AGING, overdue_1_30: 500 },
    },
    documents: [makeDocument()],
    ...overrides,
  }
}

export function makeLedger(overrides: Partial<Ledger> = {}): Ledger {
  return {
    registry: { id: 7, name: 'Rossi Srl', is_supplier: false },
    today: '2026-10-15',
    customer: makeSection(),
    supplier: null,
    ...overrides,
  }
}
