import type { ReactNode } from 'react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import type { Compliance, StaffTraining } from '@/features/staff-trainings/types'

/** A passed training with a certificate; tests override what they need. */
export function buildTraining(overrides: Partial<StaffTraining> = {}): StaffTraining {
  return {
    id: 5,
    user: { id: 7, name: 'Anna Rossi' },
    course: { id: 3, name: 'Sicurezza generale', validity_months: 60 },
    completed_on: '2026-03-10',
    expires_on: '2031-03-10',
    outcome: 'passed',
    notes: null,
    attachment: { id: 1, name: 'attestato.pdf', size: 1200 },
    recorded_by: { id: 9, name: 'Bruno Neri' },
    created_at: '2026-10-19T09:00:00Z',
    updated_at: '2026-10-19T09:00:00Z',
    ...overrides,
  }
}

export const COMPLIANCE: Compliance = {
  expiring_days: 60,
  rows: [
    { user: { id: 7, name: 'Anna Rossi' }, course: { id: 3, name: 'Sicurezza generale' }, required: true, status: 'valid', completed_on: '2026-03-10', expires_on: '2031-03-10', training_id: 5 },
    { user: { id: 7, name: 'Anna Rossi' }, course: { id: 4, name: 'Primo soccorso' }, required: true, status: 'expiring', completed_on: '2024-12-01', expires_on: '2026-12-01', training_id: 6 },
    { user: { id: 8, name: 'Carlo Bianchi' }, course: { id: 3, name: 'Sicurezza generale' }, required: true, status: 'expired', completed_on: '2020-01-10', expires_on: '2025-01-10', training_id: 7 },
    { user: { id: 8, name: 'Carlo Bianchi' }, course: { id: 4, name: 'Primo soccorso' }, required: true, status: 'missing', completed_on: null, expires_on: null, training_id: null },
  ],
}

export function httpError(status: number, data: object): AxiosError {
  return Object.assign(new AxiosError('request failed'), { response: { status, data } })
}

/** One QueryClient per test, plus the router and the confirm service the screens need. */
export function trainingWrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return function Wrapper({ children }: { children: ReactNode }) {
    return (
      <QueryClientProvider client={client}>
        <MemoryRouter>
          <ConfirmDialogProvider>{children}</ConfirmDialogProvider>
        </MemoryRouter>
      </QueryClientProvider>
    )
  }
}
