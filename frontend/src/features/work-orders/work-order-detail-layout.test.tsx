import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render as rtlRender, screen, within } from '@testing-library/react'
import type { ReactElement, ReactNode } from 'react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ConfirmContext } from '@/components/confirm-dialog-context'
import i18n from '@/i18n'
import { WORKFLOW_STATUS_FIELDS } from '@/features/work-orders/work-order-workflow-fixtures'
import { WorkOrderDetailView } from '@/features/work-orders/work-order-detail'
import type { ApplicableAttributeSummary, WorkOrderDetailWithPermissions } from '@/features/work-orders/types'

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
    status: { value: 'to_plan', is_force_closed: false },
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
    client_info: {
      legal_address: 'Via Roma 1 - 20100 Milano',
      vat_number: '01234567890',
      tax_code: null,
      tags: ['VIP'],
      emails: [],
      phones: [],
      referents: [{ id: 15, name: 'Mario Bianchi', referent_type: { id: 3, name: 'Amministratore' } }],
      commercial: { id: 30, name: 'Luca Verdi' },
      sectors: ['EA 28 — Costruzioni'],
      comments: null,
    },
    source: { id: 4, name: 'Fiera' },
    product_lines: [],
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
      actions: { view_activity: false, view_costs: true, view_contract_data: true },
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

const SITE_ACCESS_ATTRIBUTE: ApplicableAttributeSummary = {
  id: 1,
  code: 'site_access',
  name: 'Site access',
  type: 'text',
  description: null,
  help_text: null,
  placeholder: null,
  icon: null,
  config: null,
  relation_target: null,
  is_required: false,
  sort_order: 0,
  options: [],
}

/** The column a section heading sits in: the parent of its `<section>`. */
function columnOf(title: RegExp): HTMLElement | null {
  return screen.getByRole('heading', { level: 3, name: title }).closest('section')?.parentElement ?? null
}

/** Section titles of one column, in order. */
function columnTitles(column: HTMLElement | null): string[] {
  return Array.from(column?.querySelectorAll(':scope > section h3') ?? []).map((heading) => heading.textContent?.toLowerCase() ?? '')
}

function sectionTitles(): string[] {
  return screen.getAllByRole('heading', { level: 3 }).map((heading) => heading.textContent?.toLowerCase() ?? '')
}

describe('WorkOrderDetailView — record skeleton layout (spec 0331)', () => {
  // REQUIREMENT CHANGED (user directives 2026-11-16): the Info tab follows the legacy "Info" layout.
  it('renders the sections in the legacy order, left then right', () => {
    render(<WorkOrderDetailView workOrder={workOrder({ applicable_attributes: [SITE_ACCESS_ATTRIBUTE] })} />)

    const leftColumn = columnOf(/work order contract data/i)
    const rightColumn = columnOf(/company references/i)
    expect(rightColumn).not.toBe(leftColumn)
    expect(rightColumn?.parentElement).toBe(leftColumn?.parentElement)
    expect(columnTitles(leftColumn)).toEqual(['client info', 'work order contract data', 'work status', 'team'])
    expect(columnTitles(rightColumn).slice(0, 4)).toEqual([
      'company references and business functions',
      'callback date',
      'work order description',
      'additional information',
    ])
  })

  // REQUIREMENT CHANGED spec 0400 / user directives 2026-11-16: "Info cliente" replaces "Registry and contacts";
  // no Context (Source), Rewards, Document and payment or Task template on the commessa.
  it('projects the client info and drops context, rewards, document and payment', () => {
    render(<WorkOrderDetailView workOrder={workOrder()} />)

    expect(screen.getByText('Mario Bianchi')).toBeInTheDocument()
    expect(screen.getByText('Amministratore')).toBeInTheDocument()
    expect(screen.getByText('Luca Verdi')).toBeInTheDocument()
    expect(screen.queryByText('Fiera')).not.toBeInTheDocument()
    for (const dropped of ['context', 'rewards', 'document and payment']) {
      expect(sectionTitles()).not.toContain(dropped)
    }
  })

  it('moves code, type, callback date and description to the work order description on the right', () => {
    render(<WorkOrderDetailView workOrder={workOrder({ callback_date: '2026-04-10', description: 'Testo libero' })} />)

    const description = screen.getByRole('heading', { level: 3, name: /work order description/i }).closest('section') as HTMLElement
    expect(within(description).getByText('COM-0001')).toBeInTheDocument()
    expect(within(description).getByText('Processing')).toBeInTheDocument()
    expect(within(description).getByText('Testo libero')).toBeInTheDocument()
    const callback = screen.getByRole('heading', { level: 3, name: /callback date/i }).closest('section') as HTMLElement
    expect(within(callback).getByText('10/04/2026')).toBeInTheDocument()
    // REQUIREMENT CHANGED (user directive 2026-11-16): "Dati commessa" merged into "Dati contrattuali commessa".
    const data = screen.getByRole('heading', { level: 3, name: /work order contract data/i }).closest('section') as HTMLElement
    // REQUIREMENT CHANGED (spec 0403): no work order title in the contract data.
    expect(within(data).queryByText('Installazione impianto')).not.toBeInTheDocument()
    expect(within(data).getByRole('link', { name: /QUO-0004/ })).toBeInTheDocument()
    expect(within(data).queryByText('COM-0001')).not.toBeInTheDocument()
    expect(sectionTitles()).not.toContain('work order data')
  })

  // REQUIREMENT CHANGED (user directive 2026-11-18): the client company under the title, not the "COM-..." code.
  it('shows the client company under the title, the code only when there is no client', () => {
    const subtitleOf = () =>
      screen.getByRole('heading', { level: 2, name: 'Installazione impianto' }).parentElement?.nextElementSibling
        ?.textContent

    const { unmount } = render(<WorkOrderDetailView workOrder={workOrder()} />)
    expect(subtitleOf()).toBe('Acme S.p.A.')
    unmount()

    render(<WorkOrderDetailView workOrder={workOrder({ registry: null })} />)
    expect(subtitleOf()).toBe('COM-0001')
  })

  it('links the Contratto from the data section, not from a section of its own', () => {
    render(<WorkOrderDetailView workOrder={workOrder()} />)

    expect(screen.getByRole('link', { name: /QUO-0004/ })).toBeInTheDocument()
    expect(sectionTitles()).not.toContain('contract')
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
          source: null,
          registry: null,
          client_info: null,
          company: null,
        })}
      />,
    )

    const titles = sectionTitles()
    for (const dropped of ['context', 'client info', 'company references']) {
      expect(titles).not.toContain(dropped)
    }
    expect(titles).toContain('team')
  })

  it('keeps the company references on the right even without additional information (user directive 2026-11-16)', () => {
    render(<WorkOrderDetailView workOrder={workOrder()} />)

    expect(columnOf(/company references/i)).not.toBe(columnOf(/work order contract data/i))
  })

  it('shows the additional information as a fixed section, not collapsible (user directive 2026-11-05)', () => {
    render(<WorkOrderDetailView workOrder={workOrder({ applicable_attributes: [SITE_ACCESS_ATTRIBUTE] })} />)

    expect(screen.getByRole('heading', { level: 3, name: /additional information/i })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /^additional information$/i })).not.toBeInTheDocument()
  })
})
