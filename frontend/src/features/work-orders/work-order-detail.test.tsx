import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { screen, within } from '@testing-library/react'
import i18n from '@/i18n'
import { formatDateTime } from '@/features/table/cell-renderers'
import { formatDate } from '@/lib/formatting/date-display'
import { WorkOrderDetailView } from '@/features/work-orders/work-order-detail'
import { actionsMenuTrigger } from '@/features/work-orders/work-order-actions-menu-test-utils'
import { detailWorkOrder as workOrder, renderDetail as render } from '@/features/work-orders/work-order-detail-test-utils'

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

vi.mock('@/features/work-order-contract-data/work-order-contract-data-section', () => ({
  WorkOrderContractDataSection: ({ canManage }: { canManage: boolean }) => (
    <div>contract-data-section manage:{String(canManage)}</div>
  ),
}))

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

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  activityLogSectionMock.mockReset()
  canMock.mockReset()
  canMock.mockImplementation((): boolean => true)
})

describe('WorkOrderDetailView — detail fields (AC-075)', () => {
  it('shows number, title, type, status, callback date and contract', () => {
    render(<WorkOrderDetailView workOrder={workOrder()} />)

    expect(screen.getByRole('heading', { name: 'Installazione impianto' })).toBeInTheDocument()
    // The header subtitle/badges and the in-place "Details" rows both show these.
    expect(screen.getAllByText('COM-0001').length).toBeGreaterThan(0)
    expect(screen.getAllByText('Processing').length).toBeGreaterThan(0)
    expect(screen.getAllByText('To plan').length).toBeGreaterThan(0)
    expect(screen.getAllByText(formatDate('2026-09-30')).length).toBeGreaterThan(0)
    expect(screen.getByRole('link', { name: 'QUO-0004 — Fornitura annuale' })).toHaveAttribute('href', '/contracts/9')
  })

  // REQUIREMENT CHANGED (user directive 2026-11-16): the detail no longer shows the Modello di Task row.
  it('does not show the task template the commessa was generated from', () => {
    render(
      <WorkOrderDetailView
        workOrder={workOrder({ task_template: { id: 3, name: 'Onboarding cliente' } })}
      />,
    )

    expect(screen.queryByText('Task template')).not.toBeInTheDocument()
    expect(screen.queryByText('Onboarding cliente')).not.toBeInTheDocument()
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
          status: { value: 'cancelled', is_force_closed: true },
        })}
      />,
    )

    expect(screen.getByText('Force close reason')).toBeInTheDocument()
    expect(screen.getByText('Cliente insolvente')).toBeInTheDocument()
    expect(screen.getAllByText('Cancelled').length).toBeGreaterThan(0)
  })
})

// Spec 0395 (REQUIREMENT CHANGED): the badge is the working phase, no longer computed.
describe('WorkOrderDetailView — working phase and completion (spec 0149 AC-014, spec 0395)', () => {
  it.each([
    ['to_plan', 'To plan'],
    ['in_progress', 'In progress'],
    ['completed', 'Completed'],
    ['terminated', 'Terminated'],
  ] as const)('badges the %s status with its localized label', (value, label) => {
    render(<WorkOrderDetailView workOrder={workOrder({ status: { value, is_force_closed: false } })} />)

    expect(screen.getAllByText(label).length).toBeGreaterThan(0)
  })

  // User directives 2026-11-27, like the legacy: the status sits beside the "..." menu, the type is no header pill.
  it('puts the status beside the actions menu and shows no type pill in the header', () => {
    render(<WorkOrderDetailView workOrder={workOrder()} />)

    expect(actionsMenuTrigger().parentElement).toHaveTextContent('To plan')
    const band = screen.getByRole('heading', { name: 'Installazione impianto' }).closest('div.border-b')
    expect(band).not.toBeNull()
    expect(within(band as HTMLElement).queryByText('Processing')).not.toBeInTheDocument()
  })

  // REQUIREMENT CHANGED (user directive 2026-11-27, like the legacy): no KPI strip; the completion is in the
  // "Avanzamento commessa" section of the Info tab (it replaced the "Stato lavorazione" section the same day).
  it('shows no KPI strip with the completion bar, the completion only in the progress section', () => {
    render(<WorkOrderDetailView workOrder={workOrder({ completion_percentage: 40 })} />)

    expect(screen.queryByRole('progressbar', { name: 'Progress' })).not.toBeInTheDocument()
    const progress = screen.getByRole('heading', { level: 3, name: 'Work order progress' }).closest('section') as HTMLElement
    expect(within(progress).getByText('40%')).toBeInTheDocument()
    expect(screen.getAllByText('40%')).toHaveLength(1)
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
              is_synced: false,
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
              is_synced: false,
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

// REQUIREMENT CHANGED (user directive 2026-10-06, spec 0195 applied to
// Commesse): the record has no "Edit" action any more — its fields edit in
// place (`work-order-detail-inline-edit.test.tsx`).
describe('WorkOrderDetailView — no edit action', () => {
  it('renders no Edit button on the card', () => {
    render(<WorkOrderDetailView workOrder={workOrder()} />)

    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
  })
})
