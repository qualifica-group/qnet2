import type { ReactNode } from 'react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import type { StockBalance, StockMovement } from '@/features/stock/types'

/** A free row of 3 notebooks in site 4; tests override what they need. */
export function buildBalance(overrides: Partial<StockBalance> = {}): StockBalance {
  return {
    id: 1,
    product: { id: 11, code: 'NB-01', name: 'Notebook', unit_of_measure: { id: 1, code: 'pz', name: 'Pezzi' } },
    operational_site: { id: 4, label: 'Sede Roma', is_active: true },
    user: null,
    quantity: '3.000',
    last_moved_on: '2026-10-20',
    ...overrides,
  }
}

export function buildMovement(overrides: Partial<StockMovement> = {}): StockMovement {
  return {
    id: 90,
    type: 'LOAD',
    product: { id: 11, code: 'NB-01', name: 'Notebook', unit_of_measure: { id: 1, code: 'pz', name: 'Pezzi' } },
    quantity: '3.000',
    from_operational_site: null,
    from_user: null,
    to_operational_site: { id: 4, label: 'Sede Roma' },
    to_user: null,
    moved_on: '2026-10-20',
    note: null,
    created_by: { id: 2, name: 'Flavio Scognamiglio' },
    created_at: '2026-10-20T09:00:00Z',
    ...overrides,
  }
}

export function httpError(status: number, data: object): AxiosError {
  return Object.assign(new AxiosError('request failed'), { response: { status, data } })
}

/** One QueryClient per test, plus the router and the confirm service the screens need. */
export function stockWrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  const wrapper = ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <ConfirmDialogProvider>{children}</ConfirmDialogProvider>
      </MemoryRouter>
    </QueryClientProvider>
  )
  return { client, wrapper }
}
