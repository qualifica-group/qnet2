import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render as rtlRender, screen } from '@testing-library/react'
import type { ReactElement } from 'react'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { formatDateTime } from '@/features/table/cell-renderers'
import { formatDate } from '@/lib/formatting/date-display'
import { WorkOrderDetailView } from '@/features/work-orders/work-order-detail'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'

/** Every render goes through a Router: the card links related records with real `<Link>`s. */
function render(ui: ReactElement) {
  return rtlRender(ui, { wrapper: MemoryRouter })
}

// Related-record links open their target in a modal through `useModuleOpener`,
// whose mode resolver reads the authenticated user's preference. The preference
// is not what these tests are about, so the resolver is stubbed rather than
// dragging an AuthProvider into every render.
vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'modal',
}))

// Related-record links and the collaboration card's Notes tab (`work-orders.view`)
// both read the actor's client abilities. Defaults to every ability granted so the
// existing field/link assertions stay unaffected; the collaboration describe block
// below overrides it to exercise gating.
const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: canMock, hasRole: () => false, roles: [], isLoading: false }),
}))

/**
 * Spec 0093 AC-075: number, title, type, calculated status, callback date,
 * contract no., linked offer, linked product lines, description and notes
 * are all shown; the closure reason only when force-closed.
 */

const activityLogSectionMock = vi.fn()

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: (props: { resource: string; id: number }) => {
    activityLogSectionMock(props)
    return <div>activity-log-section</div>
  },
}))

// Spec 0134: the side collaboration card mounts notes and documents, which are
// not what these tests are about and would need a query client.
vi.mock('@/features/notes/notes-section', () => ({
  NotesSection: () => <div>notes-section</div>,
}))

vi.mock('@/features/attachments/documents-section', () => ({
  DocumentsSection: () => <div>documents-section</div>,
}))

vi.mock('@/features/work-orders/task-board/work-order-task-board', () => ({
  WorkOrderTaskBoard: () => <div>task-board</div>,
}))

vi.mock('@/features/work-order-costs/work-order-costs-section', () => ({
  WorkOrderCostsSection: ({ canManage }: { canManage: boolean }) => <div>costs-section manage:{String(canManage)}</div>,
}))

function workOrder(overrides: Partial<WorkOrderDetailWithPermissions> = {}): WorkOrderDetailWithPermissions {
  return {
    id: 4,
    code: 'COM-0001',
    title: 'Installazione impianto',
    type: 'processing',
    status: { value: 'open', is_force_closed: false },
    completion_percentage: 0,
    is_force_closed: false,
    force_close_reason: null,
    open_tasks_count: 0,
    callback_date: '2026-09-30',
    start_date: '2026-03-01',
    supervisors: [{ id: 21, name: 'Ada Alberti' }],
    participants: [{ id: 31, name: 'Bruno Bianchi', position: 1 }],
    description: 'Descrizione libera',
    internal_notes: 'Nota interna',
    contract_number: 'QUO-0004',
    quote: { id: 4, code: 'QUO-0004', title: 'Fornitura annuale' },
    contract: { id: 9, code: 'QUO-0004', title: 'Fornitura annuale' },
    task_template: null,
    quote_lines: [
      { id: 11, sort_order: 2, product: { id: 2, code: 'PRD-0002', name: 'Installazione' } },
      { id: 12, sort_order: 1, product: { id: 1, code: 'PRD-0001', name: 'Consulenza' } },
    ],
    applicable_attributes: [],
    attribute_layout: null,
    attribute_values: {},
    created_at: '2026-01-01T09:00:00Z',
    updated_at: '2026-02-15T14:30:00Z',
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: { view_activity: false },
    },
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  activityLogSectionMock.mockReset()
  canMock.mockReset()
  canMock.mockImplementation((): boolean => true)
})

describe('WorkOrderDetailView — detail fields (AC-075)', () => {
  it('shows number, title, type, status, callback date, contract no. and contract', () => {
    render(<WorkOrderDetailView workOrder={workOrder()} />)

    expect(screen.getByRole('heading', { name: 'Installazione impianto' })).toBeInTheDocument()
    expect(screen.getByText('COM-0001')).toBeInTheDocument()
    expect(screen.getAllByText('Processing').length).toBeGreaterThan(0)
    expect(screen.getAllByText('Open').length).toBeGreaterThan(0)
    expect(screen.getByText(formatDate('2026-09-30'))).toBeInTheDocument()
    expect(screen.getByText('QUO-0004')).toBeInTheDocument()
    expect(screen.getByText('Fornitura annuale')).toBeInTheDocument()
  })

  it('shows the task template name when the commessa was generated from one (spec 0124 D-9)', () => {
    render(
      <WorkOrderDetailView
        workOrder={workOrder({ task_template: { id: 3, name: 'Onboarding cliente' } })}
      />,
    )

    expect(screen.getByText('Task template')).toBeInTheDocument()
    expect(screen.getByText('Onboarding cliente')).toBeInTheDocument()
  })

  it('lists the linked product lines ordered by sort_order', () => {
    render(<WorkOrderDetailView workOrder={workOrder()} />)

    const lines = screen.getAllByText(/PRD-000\d/)
    expect(lines.map((el) => el.textContent)).toEqual(['PRD-0001', 'PRD-0002'])
    expect(screen.getByText('Consulenza')).toBeInTheDocument()
    expect(screen.getByText('Installazione')).toBeInTheDocument()
  })

  it('shows description, notes and both timestamps', () => {
    render(<WorkOrderDetailView workOrder={workOrder()} />)

    expect(screen.getByText('Descrizione libera')).toBeInTheDocument()
    expect(screen.getByText('Nota interna')).toBeInTheDocument()
    expect(screen.getByText(formatDateTime('2026-01-01T09:00:00Z'))).toBeInTheDocument()
    expect(screen.getByText(formatDateTime('2026-02-15T14:30:00Z'))).toBeInTheDocument()
  })

  it('shows the closure reason only when force-closed', () => {
    const { rerender } = render(<WorkOrderDetailView workOrder={workOrder()} />)
    expect(screen.queryByText('Force close reason')).not.toBeInTheDocument()

    rerender(
      <WorkOrderDetailView
        workOrder={workOrder({
          is_force_closed: true,
          force_close_reason: 'Cliente insolvente',
          status: { value: 'closed', is_force_closed: true },
        })}
      />,
    )

    expect(screen.getByText('Force close reason')).toBeInTheDocument()
    expect(screen.getByText('Cliente insolvente')).toBeInTheDocument()
    expect(screen.getAllByText('Closed').length).toBeGreaterThan(0)
  })
})

describe('WorkOrderDetailView — computed status and completion (spec 0149 AC-014)', () => {
  it.each([
    ['in_progress', 'In progress'],
    ['completed', 'Completed'],
  ] as const)('badges the %s status with its localized label', (value, label) => {
    render(<WorkOrderDetailView workOrder={workOrder({ status: { value, is_force_closed: false } })} />)

    expect(screen.getAllByText(label).length).toBeGreaterThan(0)
  })

  it('shows the completion bar with its percentage in the KPI strip', () => {
    render(<WorkOrderDetailView workOrder={workOrder({ completion_percentage: 40 })} />)

    expect(screen.getByRole('progressbar', { name: 'Completion' })).toBeInTheDocument()
    expect(screen.getByText('40%')).toHaveClass('text-warning')
  })
})

describe('WorkOrderDetailView — additional information (spec 0098, AC-023)', () => {
  it('renders no section when the work order resolves no applicable attribute', () => {
    render(<WorkOrderDetailView workOrder={workOrder()} />)

    expect(screen.queryByText('Additional information')).not.toBeInTheDocument()
  })

  it('renders one field per applicable attribute, formatted read-only', () => {
    render(
      <WorkOrderDetailView
        workOrder={workOrder({
          applicable_attributes: [
            {
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
            },
          ],
          attribute_values: { site_access: 'Gate 3' },
        })}
      />,
    )

    expect(screen.getByText('Additional information')).toBeInTheDocument()
    expect(screen.getByText('Site access')).toBeInTheDocument()
    expect(screen.getByText('Gate 3')).toBeInTheDocument()
  })

  it('lays the values out on the configured layout sections, as the form does', () => {
    render(
      <WorkOrderDetailView
        workOrder={workOrder({
          applicable_attributes: [
            {
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
            },
          ],
          attribute_values: { site_access: 'Gate 3' },
          attribute_layout: {
          sections: [
            {
              id: 's1',
              title: 'Access',
              description: null,
              variant: 'default' as const,
              collapsible: false,
              default_collapsed: false,
              columns: 2 as const,
              sort_order: 0,
              rows: [{ id: 'r1', items: [{ attribute_code: 'site_access', width: 'half' as const }] }],
            },
          ],
        },
        })}
      />,
    )

    expect(screen.getByRole('heading', { name: 'Access' })).toBeInTheDocument()
    expect(screen.getByText('Gate 3')).toBeInTheDocument()
  })
})

describe('WorkOrderDetailView — edit action on the card', () => {
  it('renders Edit in the record card and calls onEdit when the actor may update', () => {
    const onEdit = vi.fn()
    render(<WorkOrderDetailView workOrder={workOrder()} onEdit={onEdit} />)

    fireEvent.click(screen.getByRole('button', { name: 'Edit' }))

    expect(onEdit).toHaveBeenCalledTimes(1)
  })

  it('omits Edit without update permission or without an edit surface', () => {
    const readOnly = workOrder({
      permissions: { ...workOrder().permissions, resource: { ...workOrder().permissions.resource, update: false } },
    })
    const { unmount } = render(<WorkOrderDetailView workOrder={readOnly} onEdit={vi.fn()} />)
    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
    unmount()

    render(<WorkOrderDetailView workOrder={workOrder()} />)
    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
  })
})

describe('WorkOrderDetailView — related records', () => {
  // User directive 2026-09-16: the field names the Contratto, not the offer underneath it.
  it('links the contract, the task template and every line product to their records', () => {
    render(<WorkOrderDetailView workOrder={workOrder({ task_template: { id: 3, name: 'Onboarding cliente' } })} />)

    const hrefOf = (name: string) => screen.getByRole('link', { name }).getAttribute('href')
    expect(hrefOf('Fornitura annuale')).toBe('/contracts/9')
    expect(hrefOf('Onboarding cliente')).toBe('/task-templates/3')
    expect(hrefOf('Consulenza')).toBe('/products/1')
    expect(hrefOf('Installazione')).toBe('/products/2')
  })

  it("links the client registry reached through the commessa's offer", () => {
    render(<WorkOrderDetailView workOrder={workOrder({ registry: { id: 7, name: 'Acme S.p.A.' } })} />)

    expect(screen.getByText('Client registry')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Acme S.p.A.' })).toHaveAttribute('href', '/registries/7')
  })

  it('shows the empty placeholder when the commessa has no client registry', () => {
    render(<WorkOrderDetailView workOrder={workOrder({ registry: null })} />)

    expect(screen.getByText('Client registry')).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Acme S.p.A.' })).not.toBeInTheDocument()
  })

  it("links the company and both sites of the commessa's offer, as the Contract detail does", () => {
    render(
      <WorkOrderDetailView
        workOrder={workOrder({
          company: { id: 4, name: 'Qualifica Group S.r.l.' },
          company_site: { id: 5, name: 'Sede Napoli' },
          operational_site: { id: 6, label: 'Via Roma 1 - Napoli' },
        })}
      />,
    )

    expect(screen.getByText('Company and sites')).toBeInTheDocument()
    const hrefOf = (name: string) => screen.getByRole('link', { name }).getAttribute('href')
    expect(hrefOf('Qualifica Group S.r.l.')).toBe('/companies/4')
    expect(hrefOf('Sede Napoli')).toBe('/company-sites/5')
    expect(hrefOf('Via Roma 1 - Napoli')).toBe('/operational-sites/6')
  })

  it('shows the company and sites rows with the empty placeholder when the offer has none', () => {
    render(<WorkOrderDetailView workOrder={workOrder({ company: null, company_site: null, operational_site: null })} />)

    expect(screen.getByText('Company')).toBeInTheDocument()
    expect(screen.getByText('Site')).toBeInTheDocument()
    expect(screen.getByText('Operational site')).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: /Sede|Via Roma/ })).not.toBeInTheDocument()
  })
})

/**
 * The collaboration card mirrors `OpportunityDetailView`'s: one card, a
 * Notes | Documents | Activity tab strip, each tab gated by its own
 * authorization source (spec 0134 D-3), absent as a whole when nothing is
 * authorized.
 */
describe('WorkOrderDetailView — collaboration', () => {
  it('renders no collaboration card when nothing is authorized', () => {
    canMock.mockImplementation((): boolean => false)
    render(<WorkOrderDetailView workOrder={workOrder()} />)

    expect(screen.queryByRole('tablist')).not.toBeInTheDocument()
  })

  it('shows the Notes tab, selected by default, when work-orders.view is granted', () => {
    canMock.mockImplementation((permission: string) => permission === 'work-orders.view')
    render(<WorkOrderDetailView workOrder={workOrder()} />)

    const notesTab = screen.getByRole('tab', { name: 'Notes' })
    expect(notesTab).toHaveAttribute('aria-selected', 'true')
    expect(screen.queryByRole('tab', { name: 'Documents' })).not.toBeInTheDocument()
    expect(screen.queryByRole('tab', { name: 'Activity log' })).not.toBeInTheDocument()
  })

  it("shows the Documents tab, reading the work order's own view_documents gate", () => {
    canMock.mockImplementation((): boolean => false)
    render(<WorkOrderDetailView workOrder={workOrder({ permissions: { ...workOrder().permissions, actions: { view_documents: true } } })} />)

    expect(screen.getByRole('tab', { name: 'Documents' })).toBeInTheDocument()
    expect(screen.queryByRole('tab', { name: 'Notes' })).not.toBeInTheDocument()
  })

  it("shows the read-only Registry documents tab on the commessa's registry with registries.viewDocuments (spec 0173)", () => {
    canMock.mockImplementation((permission: string) => permission === 'registries.viewDocuments')
    render(<WorkOrderDetailView workOrder={workOrder({ registry: { id: 7, name: 'Acme S.p.A.' } })} />)

    expect(screen.getByRole('tab', { name: 'Registry documents' })).toBeInTheDocument()
  })

  it('omits the Registry documents tab when the payload carries no registry', () => {
    canMock.mockImplementation((permission: string) => permission === 'registries.viewDocuments')
    render(<WorkOrderDetailView workOrder={workOrder({ registry: null })} />)

    expect(screen.queryByRole('tab', { name: 'Registry documents' })).not.toBeInTheDocument()
  })

  it('mounts the Activity log section only inside the collaboration card, reading its own view_activity gate', () => {
    canMock.mockImplementation((): boolean => false)
    render(
      <WorkOrderDetailView
        workOrder={workOrder({ permissions: { ...workOrder().permissions, actions: { view_activity: true } } })}
      />,
    )

    fireEvent.mouseDown(screen.getByRole('tab', { name: 'Activity log' }))

    expect(activityLogSectionMock).toHaveBeenCalledWith({ resource: 'work-orders', id: 4 })
    // The Task board sits below the record, outside the collaboration card.
  })

  it('hides the Activity log tab when view_activity is not granted', () => {
    render(<WorkOrderDetailView workOrder={workOrder()} />)

    expect(screen.queryByRole('tab', { name: 'Activity log' })).not.toBeInTheDocument()
    expect(activityLogSectionMock).not.toHaveBeenCalled()
  })

  it('renders the Task board below the record regardless of the collaboration card', () => {
    canMock.mockImplementation((permission: string) => permission === 'tasks.viewAny')
    render(<WorkOrderDetailView workOrder={workOrder()} />)

    expect(screen.getByText('task-board')).toBeInTheDocument()
  })
})

/**
 * Spec 0190 AC-011: the Costi section follows `permissions.actions.view_costs` alone.
 * REQUIREMENT CHANGED (user directive 2026-10-06): Costi is a tab next to the
 * Task board in one card, Task open by default, so the Costi tab is selected first.
 */
describe('WorkOrderDetailView — Costi section (spec 0190)', () => {
  function withActions(actions: Record<string, boolean>) {
    const base = workOrder()
    return workOrder({ permissions: { ...base.permissions, actions } })
  }

  function openCostsTab() {
    fireEvent.mouseDown(screen.getByRole('tab', { name: 'Costs' }))
  }

  it('is not rendered without view_costs', () => {
    render(<WorkOrderDetailView workOrder={withActions({ manage_costs: true })} />)

    expect(screen.queryByRole('tab', { name: 'Costs' })).not.toBeInTheDocument()
    expect(screen.queryByText(/costs-section/)).not.toBeInTheDocument()
  })

  it('is rendered read-only with view_costs alone', () => {
    render(<WorkOrderDetailView workOrder={withActions({ view_costs: true })} />)
    openCostsTab()

    expect(screen.getByText('costs-section manage:false')).toBeInTheDocument()
  })

  it('is editable with view_costs and manage_costs', () => {
    render(<WorkOrderDetailView workOrder={withActions({ view_costs: true, manage_costs: true })} />)
    openCostsTab()

    expect(screen.getByText('costs-section manage:true')).toBeInTheDocument()
  })
})
