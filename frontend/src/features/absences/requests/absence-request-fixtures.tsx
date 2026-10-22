import type { ReactNode } from 'react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import type { AbsenceBalances, AbsenceRequest, AbsenceRequestInsight, AbsenceTypeOption } from '@/features/absences/types'

/** A submitted full-day request with every ability off; tests flip the ones they need. */
export function buildRequest(overrides: Partial<AbsenceRequest> = {}): AbsenceRequest {
  return {
    id: 5,
    user: { id: 7, name: 'Anna Rossi' },
    absence_type: { id: 1, name: 'Ferie', deducts_balance: true, requires_approval: true, color: 'slate' },
    status: 'submitted',
    is_full_day: true,
    day_part: null,
    is_short_notice: false,
    company_closure: null,
    daily_minutes: 480,
    starts_on: '2026-11-02',
    ends_on: '2026-11-04',
    minutes: 1440,
    requested_minutes: null,
    note: null,
    rejection_reason: null,
    approvers: [{ id: 9, name: 'Bruno Neri' }],
    decided_by: null,
    decided_at: null,
    submitted_at: '2026-10-19T09:00:00Z',
    cancelled_at: null,
    attachment: null,
    can: { update: false, delete: false, submit: false, approve: false, reject: false, cancel: false, attach: false },
    created_at: '2026-10-19T09:00:00Z',
    updated_at: '2026-10-19T09:00:00Z',
    ...overrides,
  }
}

/** Active type admitting every mode, as returned by `for-select`. */
export function buildTypeOption(overrides: Partial<AbsenceTypeOption> = {}): AbsenceTypeOption {
  return {
    id: 1,
    name: 'Ferie',
    deducts_balance: true,
    requires_approval: true,
    allows_full_day: true,
    allows_half_day: true,
    allows_hourly: true,
    min_notice_days: null,
    color: 'slate',
    ...overrides,
  }
}

/** A clean preview: 3 working days of a deducting type, nothing to warn about. */
export function buildInsight(overrides: Partial<AbsenceRequestInsight> = {}): AbsenceRequestInsight {
  return {
    person: { id: 7, name: 'Anna Rossi' },
    daily_minutes: 480,
    working_days: 3,
    minutes: 1440,
    non_working_days: [],
    balance: { year: 2026, residual_minutes: 4800, residual_after_minutes: 3360 },
    notice: { min_notice_days: null, is_short_notice: false },
    overlaps: [],
    colleagues_absent: [],
    ...overrides,
  }
}

export const BALANCES: AbsenceBalances = {
  user: { id: 7, name: 'Anna Rossi' },
  year: 2026,
  daily_minutes: 480,
  balances: [
    {
      absence_type: { id: 1, name: 'Ferie', color: 'slate' },
      source: 'allowance',
      annual_minutes: 9600,
      carried_over_minutes: 0,
      accrued_minutes: 5000,
      used_minutes: 600,
      pending_minutes: 0,
      residual_minutes: 4400,
    },
  ],
}

export function httpError(status: number, data: object): AxiosError {
  return Object.assign(new AxiosError('request failed'), { response: { status, data } })
}

/** One QueryClient per test, plus the router and the confirm service the screens need. */
export function absenceWrapper(initialEntry = '/absences') {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return function Wrapper({ children }: { children: ReactNode }) {
    return (
      <QueryClientProvider client={client}>
        <MemoryRouter initialEntries={[initialEntry]}>
          <ConfirmDialogProvider>{children}</ConfirmDialogProvider>
        </MemoryRouter>
      </QueryClientProvider>
    )
  }
}
