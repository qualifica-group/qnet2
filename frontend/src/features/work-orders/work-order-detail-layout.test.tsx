import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render as rtlRender, screen } from '@testing-library/react'
import type { ReactElement, ReactNode } from 'react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ConfirmContext } from '@/components/confirm-dialog-context'
import i18n from '@/i18n'
import { WORKFLOW_STATUS_FIELDS } from '@/features/work-orders/work-order-workflow-fixtures'
import { WorkOrderDetailView } from '@/features/work-orders/work-order-detail'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'

/**
 * Spec 0331 AC-006/AC-007/AC-009: the work order card follows the shared
 * record skeleton, projects the offer's data read-only and, on a multi-offer
 * work order, drops the sections that need a single offer.
 */

function render(ui: ReactElement) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  function Wrapper({ children }: { children: ReactNode }) {
    return (
      <MemoryRouter>
        <QueryClientProvider client={client}>
          <ConfirmContext.Provider value={() => Promise.resolve(true)}>{children}</ConfirmContext.Provider>
        </QueryClientProvider>
      </MemoryRouter>
    )
  }
  return rtlRender(ui, { wrapper: Wrapper })
}

vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'modal' }))

const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: canMock, hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('@/features/work-order-contract-data/work-order-contract-data-section', () => ({
  WorkOrderContractDataSection: () => <div>contract-data-section</div>,
}))
vi.mock('@/features/activity-log/activity-log-section', () => ({ ActivityLogSection: () => <div>activity</div> }))
vi.mock('@/features/notes/notes-section', () => ({ NotesSection: () => <div>notes-section</div> }))
vi.mock('@/features/attachments/documents-section', () => ({ DocumentsSection: () => <div>documents-section</div> }))
vi.mock('@/features/work-orders/task-board/work-order-task-board', () => ({
  WorkOrderTaskBoard: () => <div>task-board</div>,
}))
vi.mock('@/features/work-order-costs/work-order-costs-section', () => ({
  WorkOrderCostsSection: () => <div>costs-section</div>,
}))

function workOrder(overrides: Partial<WorkOrderDetailWithPermissions> = {}): WorkOrderDetailWithPermissions {
  return {
    id: 4,
    code: 'COM-0001',
    title: 'Installazione impianto',
    title_is_manual: true,
    type: 'processing',
    status: { value: 'open', is_force_closed: false },
    completion_percentage: 0,
    is_force_closed: false,
    ...WORKFLOW_STATUS_FIELDS,
    force_close_reason: null,
    open_tasks_count: 0,
    callback_date: null,
    start_date: '2026-03-01',
    supervisors: [{ id: 21, name: 'Ada Alberti' }],
    participants: [],
    description: null,
    internal_notes: null,
    is_multi_quote: false,
    source_quotes: [],
    contract_number: 'QUO-0004',
    quote: { id: 4, code: 'QUO-0004', title: 'Fornitura annuale' },
    contract: { id: 9, code: 'QUO-0004', title: 'Fornitura annuale', expiry_date: '2027-03-31' },
    registry: { id: 1, name: 'Acme S.p.A.' },
    referent: { id: 15, name: 'Mario Bianchi', referent_type: { id: 3, name: 'Amministratore' } },
    commercial: { id: 30, name: 'Luca Verdi' },
    reporter: { id: 40, name: 'Giulia Neri' },
    source: { id: 4, name: 'Fiera' },
    product_lines: [],
    rewards: [],
    layout: { id: 9, name: 'Layout standard' },
    payment_method: { id: 7, name: 'Bonifico 30 gg' },
    company: { id: 2, name: 'Acme Holding' },
    task_template: null,
    quote_lines: [],
    applicable_attributes: [],
    attribute_layout: null,
    attribute_values: {},
    created_at: '2026-01-01T09:00:00Z',
    updated_at: '2026-02-15T14:30:00Z',
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: { view_activity: false, view_contract_data: true },
    },
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
  canMock.mockImplementation((): boolean => true)
})

function sectionTitles(): string[] {
  return screen.getAllByRole('heading', { level: 3 }).map((heading) => heading.textContent?.toLowerCase() ?? '')
}

describe('WorkOrderDetailView — record skeleton layout (spec 0331)', () => {
  it('renders the sections in the canonical order', () => {
    render(<WorkOrderDetailView workOrder={workOrder()} />)

    const titles = sectionTitles()
    const expected = [
      'work order data',
      'context',
      'registry and contacts',
      'rewards',
      'team',
      'company and sites',
      'document and payment',
    ]
    const positions = expected.map((title) => titles.indexOf(title))

    expect(positions.every((position) => position >= 0)).toBe(true)
    expect(positions).toEqual([...positions].sort((a, b) => a - b))
  })

  it('projects registry, referent with its type, commercial, reporter, source, layout and payment method', () => {
    render(<WorkOrderDetailView workOrder={workOrder()} />)

    expect(screen.getByText('Mario Bianchi')).toBeInTheDocument()
    expect(screen.getByText('Amministratore')).toBeInTheDocument()
    expect(screen.getByText('Luca Verdi')).toBeInTheDocument()
    expect(screen.getByText('Giulia Neri')).toBeInTheDocument()
    expect(screen.getByText('Fiera')).toBeInTheDocument()
    expect(screen.getByText('Layout standard')).toBeInTheDocument()
    expect(screen.getByText('Bonifico 30 gg')).toBeInTheDocument()
  })

  it('links the Contratto from the data section, not from a section of its own', () => {
    render(<WorkOrderDetailView workOrder={workOrder()} />)

    expect(screen.getByRole('link', { name: /QUO-0004/ })).toBeInTheDocument()
    expect(sectionTitles()).not.toContain('contract')
  })

  it('shows the empty rewards placeholder when the offer has none', () => {
    render(<WorkOrderDetailView workOrder={workOrder({ rewards: [] })} />)

    expect(screen.getByText('No rewards')).toBeInTheDocument()
  })

  it('has no pencil on the projected rows', () => {
    render(<WorkOrderDetailView workOrder={workOrder()} />)

    expect(screen.queryByRole('button', { name: /edit (commercial|reporter|layout|payment method)/i })).not.toBeInTheDocument()
  })

  it('drops the offer-projected sections on a multi-offer work order', () => {
    render(
      <WorkOrderDetailView
        workOrder={workOrder({
          is_multi_quote: true,
          contract: null,
          quote: null,
          referent: null,
          commercial: null,
          reporter: null,
          source: null,
          layout: null,
          payment_method: null,
          registry: null,
          company: null,
        })}
      />,
    )

    const titles = sectionTitles()
    for (const dropped of ['context', 'registry and contacts', 'rewards', 'company and sites', 'document and payment']) {
      expect(titles).not.toContain(dropped)
    }
    expect(titles).toContain('team')
    expect(screen.queryByText('No rewards')).not.toBeInTheDocument()
  })
})
