import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { TaskKanbanView } from '@/features/tasks/task-kanban/task-kanban-view'
import type { TaskKanbanRow } from '@/features/tasks/task-kanban/task-kanban-types'

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

const rowsMock = vi.fn()
vi.mock('@/features/tasks/task-kanban/use-task-kanban-rows', () => ({
  useTaskKanbanRows: () => rowsMock(),
  TASK_KANBAN_ROW_LIMIT: 500,
}))

const statusesMock = vi.fn()
vi.mock('@/features/tasks/task-kanban/use-task-kanban-statuses', () => ({
  useTaskKanbanStatuses: () => statusesMock(),
}))

vi.mock('@/features/tasks/task-kanban/use-task-kanban-status-move', () => ({
  useTaskKanbanStatusMove: () => ({
    moveToStatus: vi.fn(),
    completingTask: null,
    closeCompleteDialog: vi.fn(),
    handleCompleted: vi.fn(),
  }),
}))

vi.mock('@/features/tasks/task-kanban/use-task-kanban-due-move', () => ({
  useTaskKanbanDueMove: () => ({ moveToBucket: vi.fn() }),
}))

function openRow(id: number): TaskKanbanRow {
  return {
    id,
    actions: [],
    title: `Task ${id}`,
    task_status: { id: 1, name: 'Aperto', color: 'blue', icon: null, group: 'open' },
    task_priority: null,
    start_date: null,
    end_date: null,
    completion_percentage: 0,
    estimated_minutes: null,
    actual_minutes: 0,
    is_blocked: false,
    assignees: [],
    has_subtasks: false,
  }
}

const baseRowsData = {
  rows: [openRow(1)],
  total: 1,
  exceededLimit: false,
  isPending: false,
  isError: false,
  refetch: vi.fn(),
  refresh: vi.fn(),
  search: '',
  setSearch: vi.fn(),
  descriptors: [],
  advancedFilters: { activeCount: 0, activeValues: {} },
}

beforeEach(async () => {
  await i18n.changeLanguage('it')
  rowsMock.mockReturnValue(baseRowsData)
  statusesMock.mockReturnValue({
    statuses: [
      { id: 1, label: 'Aperto', meta: { system_key: 'open', group: 'open', completion_percentage: 0, color: 'blue', icon: null } },
      { id: 2, label: 'Chiuso', meta: { system_key: 'closed_positive', group: 'closed_positive', completion_percentage: 100, color: 'green', icon: null } },
    ],
  })
})

describe('TaskKanbanView (spec 0157 D-2..D-5)', () => {
  it('renders one column per active status, in catalog order (per stato)', () => {
    render(<TaskKanbanView mode="status" onOpenTask={vi.fn()} onCreateTask={vi.fn()} />)

    expect(screen.getByRole('heading', { name: 'Aperto' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Chiuso' })).toBeInTheDocument()
    expect(screen.getByText('Task 1')).toBeInTheDocument()
  })

  it('renders the seven fixed due-date buckets (per scadenza), including "Più avanti"', () => {
    render(<TaskKanbanView mode="due" onOpenTask={vi.fn()} onCreateTask={vi.fn()} />)

    expect(screen.getByText('Scaduti')).toBeInTheDocument()
    expect(screen.getByText('Oggi')).toBeInTheDocument()
    expect(screen.getByText('Domani')).toBeInTheDocument()
    expect(screen.getByText('Questa settimana')).toBeInTheDocument()
    expect(screen.getByText('Questo mese')).toBeInTheDocument()
    expect(screen.getByText('Più avanti')).toBeInTheDocument()
    expect(screen.getByText('Completati')).toBeInTheDocument()
  })

  it('shows the "restringi i filtri" warning once the total exceeds the 500 cap', () => {
    rowsMock.mockReturnValue({ ...baseRowsData, total: 640, exceededLimit: true })

    render(<TaskKanbanView mode="status" onOpenTask={vi.fn()} onCreateTask={vi.fn()} />)

    expect(screen.getByText(/640/)).toBeInTheDocument()
  })

  it('the "+" opens the create form pre-filled with the status column (D-4)', () => {
    const onCreateTask = vi.fn()
    render(<TaskKanbanView mode="status" onOpenTask={vi.fn()} onCreateTask={onCreateTask} />)

    // Only the manual (open/pending) status column offers "+"; "Chiuso" (closed_positive) never does.
    const addButtons = screen.getAllByRole('button', { name: 'Nuovo task' })
    expect(addButtons).toHaveLength(1)

    addButtons[0].click()
    expect(onCreateTask).toHaveBeenCalledWith({ task_status_id: 1 })
  })
})
