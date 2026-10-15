import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { beforeAll, describe, expect, it, vi } from 'vitest'
import i18n from '@/i18n'
import { ProformaRequestDetailView } from '@/features/proforma-requests/proforma-request-detail'
import type { ProformaRequestWithPermissions } from '@/features/proforma-requests/types'

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

const request: ProformaRequestWithPermissions = {
  id: 1,
  kind: 'consultancy',
  status: 'pending',
  issued_at: null,
  note: 'Nota',
  work_order: { id: 4, code: 'COM-0001', title: 'Installazione' },
  quote: { id: 7, code: 'QUO-0007' },
  company: { id: 2, name: 'Qualifica Group' },
  customer: { id: 3, name: 'Rossi S.r.l.' },
  supplier: null,
  payment_method: null,
  assigned_to: { id: 1, name: 'Ada' },
  assigned_by: { id: 2, name: 'Bruno' },
  created_at: '2026-01-01T09:00:00Z',
  updated_at: '2026-01-01T09:00:00Z',
  permissions: {
    resource: { view: true, create: false, update: false, delete: false, export: false, import: false },
    fields: {},
    actions: { view_activity: false },
  },
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('ProformaRequestDetailView', () => {
  it('shows the offer code the request invoices', () => {
    render(
      <MemoryRouter>
        <QueryClientProvider client={new QueryClient()}>
          <ProformaRequestDetailView proformaRequest={request} />
        </QueryClientProvider>
      </MemoryRouter>,
    )

    expect(screen.getByText('Quote')).toBeInTheDocument()
    expect(screen.getByText('QUO-0007')).toBeInTheDocument()
  })
})
