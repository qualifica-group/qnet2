import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ConfirmContext, type ConfirmFn } from '@/components/confirm-dialog-context'
import i18n from '@/i18n'
import { contracts as contractsEn } from '@/i18n/locales/en-contracts'
import { ContractDetailView } from '@/features/contracts/contract-detail'
import type { ContractDetailWithPermissions } from '@/features/contracts/types'

/**
 * Spec 0331 AC-006/AC-007/AC-008: the contract card follows the shared record
 * skeleton, projects the offer's context/team/document read-only and keeps
 * the payment notes editable.
 */

vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'modal' }))

const { canMock } = vi.hoisted(() => ({ canMock: vi.fn<(permission: string) => boolean>() }))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: canMock, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('@/components/ui/tabs', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/components/ui/tabs')>()
  return { ...actual, TabsContent: ({ children }: { children: ReactNode }) => <div>{children}</div> }
})
vi.mock('@/features/attachments/documents-section', () => ({ DocumentsSection: () => <div>documents</div> }))
vi.mock('@/features/activity-log/activity-log-section', () => ({ ActivityLogSection: () => <div>activity</div> }))
vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<{ refresh: () => void; clearSelection: () => void }, object>(function TableViewStub(_p, ref) {
    useImperativeHandle(ref, () => ({ refresh: vi.fn(), clearSelection: vi.fn() }))
    return <div>work-orders-table-stub</div>
  }),
}))

const confirmMock = vi.fn<ConfirmFn>()

function contract(overrides: Partial<ContractDetailWithPermissions> = {}): ContractDetailWithPermissions {
  return {
    id: 1,
    quote_id: 3,
    quote: {
      id: 3,
      code: 'QUO-0003',
      title: 'Accordo Acme',
      created_at: '2026-01-05T00:00:00Z',
      revenue_net: '1000.00',
      revenue_vat: '220.00',
      revenue_gross: '1220.00',
      cost_net: '400.00',
      margin_net: '600.00',
    },
    registry: { id: 10, name: 'Acme S.p.A.' },
    referent: null,
    opportunity: { id: 20, name: 'Fornitura 2026' },
    company: null,
    company_site: null,
    operational_site: null,
    commercial: null,
    reporter: null,
    supervisor: { id: 50, name: 'Paolo Blu' },
    payment_method: { id: 7, name: 'Bonifico 30 gg' },
    source: { id: 4, name: 'Fiera' },
    product_lines: [],
    rewards: [],
    managers: [
      { id: 61, name: 'Anna Rossi', position: 2 },
      { id: 60, name: 'Carlo Gialli', position: 1 },
    ],
    manager_labels: { '1': 'G.A. Alfa' },
    layout: { id: 9, name: 'Layout standard' },
    contract_status_id: 1,
    contract_status: { id: 1, name: 'Da validare', color: 'slate', group: 'open' },
    accepted_at: '2026-01-10',
    validated_at: null,
    renewal_date: null,
    expiry_date: null,
    terminated_at: null,
    termination_reason: null,
    payment_notes: null,
    comments: null,
    validated_by: null,
    terminated_by: null,
    suspended_at: null,
    status_before_suspension: null,
    is_suspended: false,
    alert: null,
    days_to_expiry: null,
    days_to_renewal: null,
    offer_lines: [],
    summary: {
      revenue: { net: '1000.00', vat: '220.00', gross: '1220.00' },
      cost: { net: '400.00', vat: '88.00', gross: '488.00' },
      margin: { net: '600.00' },
    },
    created_at: '2026-01-10T00:00:00Z',
    updated_at: '2026-01-10T00:00:00Z',
    permissions: {
      resource: { view: true, create: false, update: true, delete: false, export: true, import: false },
      fields: {},
      actions: {
        validate: true,
        program: true,
        terminate: true,
        reactivate: true,
        change_status: true,
        export: true,
        view_activity: true,
      },
    },
    ...overrides,
  }
}

function renderView(data: ContractDetailWithPermissions) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <ConfirmContext.Provider value={confirmMock}>
          <ContractDetailView contract={data} />
        </ConfirmContext.Provider>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
  i18n.addResourceBundle('en', 'translation', { contracts: contractsEn }, true, true)
})

beforeEach(() => {
  canMock.mockReset()
  canMock.mockReturnValue(true)
  confirmMock.mockReset()
})

const SECTION_TITLES = [
  'Contract data',
  'Context',
  'Registry and contacts',
  'Rewards',
  'Team',
  'Company and sites',
  'Document and payment',
  'Lifecycle',
]

describe('ContractDetailView — record skeleton layout (spec 0331)', () => {
  it('renders the sections in the canonical order', () => {
    renderView(contract())

    const titles = screen.getAllByRole('heading', { level: 3 }).map((heading) => heading.textContent)
    const positions = SECTION_TITLES.map((title) => titles.findIndex((text) => text?.toLowerCase() === title.toLowerCase()))

    expect(positions.every((position) => position >= 0)).toBe(true)
    expect(positions).toEqual([...positions].sort((a, b) => a - b))
  })

  it('keeps the Rewards section visible with its empty label when there are none', () => {
    renderView(contract({ rewards: [] }))

    expect(screen.getByText('No rewards')).toBeInTheDocument()
  })

  it('shows the offer team read-only, ordered by slot with the slot labels', () => {
    renderView(contract())

    expect(screen.getByText('Paolo Blu')).toBeInTheDocument()
    expect(screen.getByText('G.A. Alfa')).toBeInTheDocument()
    const carlo = screen.getByText('Carlo Gialli')
    const anna = screen.getByText('Anna Rossi')
    expect(carlo.compareDocumentPosition(anna) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy()
    expect(screen.queryByRole('button', { name: /edit supervisor/i })).not.toBeInTheDocument()
  })

  it('projects source, layout and payment method, and keeps the payment notes editable', () => {
    renderView(contract())

    expect(screen.getByText('Fiera')).toBeInTheDocument()
    expect(screen.getByText('Layout standard')).toBeInTheDocument()
    expect(screen.getByText('Bonifico 30 gg')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /payment notes/i })).toBeInTheDocument()
  })
})
