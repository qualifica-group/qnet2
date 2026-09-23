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

// Related-record links render only for an actor who can view the target
// module; these tests are not about abilities, so every ability is granted.
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
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
})

describe('WorkOrderDetailView — activity log section', () => {
  // Spec 0134 D-3 (REQUIREMENT CHANGED): the log moved from the bottom of the
  // record card into the side collaboration card's own "Activity" tab.
  it('mounts the section when view_activity is granted', () => {
    render(
      <WorkOrderDetailView
        workOrder={workOrder({
          permissions: { ...workOrder().permissions, actions: { view_activity: true } },
        })}
      />,
    )

    fireEvent.mouseDown(screen.getByRole('tab', { name: 'Activity log' }))

    expect(activityLogSectionMock).toHaveBeenCalledWith({ resource: 'work-orders', id: 4 })
  })

  it('hides the section when view_activity is not granted', () => {
    render(<WorkOrderDetailView workOrder={workOrder()} />)

    expect(activityLogSectionMock).not.toHaveBeenCalled()
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
})
