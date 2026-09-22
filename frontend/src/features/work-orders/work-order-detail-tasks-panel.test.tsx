import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render as rtlRender, screen } from '@testing-library/react'
import type { ReactElement } from 'react'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { WorkOrderDetailView } from '@/features/work-orders/work-order-detail'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'

/**
 * Spec 0133 D-2, REQUIREMENT CHANGED (user directive 2026-09-16, "same template
 * as Opportunita'/Offerte"): the work order's tasks are no longer a
 * "Details | Tasks" strip inside the record card but a full-width panel below
 * the record, like the Offerte panel of the Opportunita' record. With
 * `tasks.viewAny` the panel mounts next to the sections; without, it is absent.
 *
 * Spec 0146 D-10, REQUIREMENT CHANGED: the panel is now the Task board
 * (`WorkOrderTaskBoard`), not the old `TableView domain="tasks"` grid
 * (`WorkOrderTasksSection`, removed) — same gate, same mount point (AC-024).
 */
function render(ui: ReactElement) {
  return rtlRender(ui, { wrapper: MemoryRouter })
}

vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'modal',
}))

const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => canMock(permission), hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: () => <div>activity-log-section</div>,
}))

vi.mock('@/features/notes/notes-section', () => ({
  NotesSection: () => <div>notes-section</div>,
}))

vi.mock('@/features/attachments/documents-section', () => ({
  DocumentsSection: () => <div>documents-section</div>,
}))

vi.mock('@/features/work-orders/task-board/work-order-task-board', () => ({
  WorkOrderTaskBoard: ({ workOrderId }: { workOrderId: number }) => <div>task-board-{workOrderId}</div>,
}))

function workOrder(): WorkOrderDetailWithPermissions {
  return {
    id: 4,
    code: 'COM-0001',
    title: 'Installazione impianto',
    type: 'processing',
    status: { value: 'open', is_force_closed: false },
    is_force_closed: false,
    force_close_reason: null,
    open_tasks_count: 0,
    callback_date: null,
    start_date: '2026-03-01',
    supervisors: [],
    participants: [],
    description: 'Descrizione libera',
    internal_notes: null,
    contract_number: null,
    quote: null,
    contract: null,
    task_template: null,
    quote_lines: [],
    applicable_attributes: [],
    attribute_layout: null,
    attribute_values: {},
    created_at: '2026-01-01T09:00:00Z',
    updated_at: '2026-01-01T09:00:00Z',
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: { view_activity: true },
    },
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
  canMock.mockReturnValue(true)
})

describe('WorkOrderDetailView — task panel (spec 0133)', () => {
  it('AC-009: mounts the task panel below the record, with the sections always visible', () => {
    render(<WorkOrderDetailView workOrder={workOrder()} />)

    expect(screen.getByText('task-board-4')).toBeInTheDocument()
    expect(screen.getByText('Descrizione libera')).toBeInTheDocument()
    expect(screen.queryByRole('tab', { name: 'Details' })).not.toBeInTheDocument()
    expect(screen.getByText('notes-section')).toBeInTheDocument()
  })

  it('AC-010: without tasks.viewAny there is no task panel and the record renders unchanged', () => {
    canMock.mockImplementation((permission) => permission !== 'tasks.viewAny')
    render(<WorkOrderDetailView workOrder={workOrder()} />)

    expect(screen.queryByText('task-board-4')).not.toBeInTheDocument()
    expect(screen.getByText('Descrizione libera')).toBeInTheDocument()
    expect(screen.getByRole('tab', { name: 'Activity log' })).toBeInTheDocument()
  })
})
