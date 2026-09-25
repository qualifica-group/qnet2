import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { TaskKanbanView } from '@/features/tasks/task-kanban/task-kanban-view'
import type { TaskKanbanRow } from '@/features/tasks/task-kanban/task-kanban-types'
import type { TaskKanbanMode } from '@/features/tasks/use-task-kanban-mode'
import type { ModuleCreateParams } from '@/features/modules/types'

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

const filtersMock = vi.fn()
vi.mock('@/features/tasks/task-kanban/use-task-kanban-filters', () => ({
  useTaskKanbanFilters: () => filtersMock(),
}))

const statusesMock = vi.fn()
vi.mock('@/features/tasks/task-kanban/use-task-kanban-statuses', () => ({
  useTaskKanbanStatuses: () => statusesMock(),
}))

const statusMoveMock = vi.fn()
vi.mock('@/features/tasks/task-kanban/use-task-kanban-status-move', () => ({
  useTaskKanbanStatusMove: () => statusMoveMock(),
}))

const dueMoveMock = vi.fn()
vi.mock('@/features/tasks/task-kanban/use-task-kanban-due-move', () => ({
  useTaskKanbanDueMove: () => dueMoveMock(),
}))

interface CapturedDndArgs {
  onDrop?: (row: TaskKanbanRow, originKey: string, targetKey: string) => void
}
const capturedDnd: CapturedDndArgs = {}
vi.mock('@/features/tasks/task-kanban/use-task-kanban-dnd', () => ({
  useTaskKanbanDnd: (args: CapturedDndArgs) => {
    capturedDnd.onDrop = args.onDrop
    return { sensors: [], activeRow: null, handleDragStart: vi.fn(), handleDragEnd: vi.fn() }
  },
  taskKanbanColumnDroppableId: (key: string) => `kanban-column-${key}`,
}))

const columnRowsMock = vi.fn()
vi.mock('@/features/tasks/task-kanban/use-task-kanban-column-rows', () => ({
  useTaskKanbanColumnRows: (args: unknown) => columnRowsMock(args),
  taskKanbanColumnQueryKey: (kanbanGroup: unknown) => ['tasks', 'kanban', 'column', kanbanGroup],
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

function columnState(rows: TaskKanbanRow[] = []) {
  return {
    data: {
      pages: [{ items: rows, export_link: null, pagination: { total: rows.length, offset: 0, limit: 50, total_pages: 1 } }],
    },
    isPending: false,
    isError: false,
    hasNextPage: false,
    isFetchingNextPage: false,
    fetchNextPage: vi.fn(),
    refetch: vi.fn(),
  }
}

const baseFilters = {
  isReady: true,
  isPending: false,
  isError: false,
  refetch: vi.fn(),
  search: '',
  setSearch: vi.fn(),
  descriptors: [],
  advancedFilters: { activeCount: 0, activeValues: {} },
  sortModel: [],
  filterModel: {},
  trimmedSearch: '',
}

function renderView(
  props: Partial<{ mode: TaskKanbanMode; onOpenTask: (id: number) => void; onCreateTask: (params: ModuleCreateParams) => void }> = {},
  queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } }),
) {
  return render(
    <QueryClientProvider client={queryClient}>
      <TaskKanbanView
        mode={props.mode ?? 'status'}
        onOpenTask={props.onOpenTask ?? vi.fn()}
        onCreateTask={props.onCreateTask ?? vi.fn()}
      />
    </QueryClientProvider>,
  )
}

beforeEach(async () => {
  await i18n.changeLanguage('it')
  filtersMock.mockReturnValue(baseFilters)
  statusMoveMock.mockReturnValue({
    moveToStatus: vi.fn(),
    completingTask: null,
    closeCompleteDialog: vi.fn(),
    handleCompleted: vi.fn(),
  })
  dueMoveMock.mockReturnValue({ moveToBucket: vi.fn() })
  columnRowsMock.mockReturnValue(columnState([]))
  statusesMock.mockReturnValue({
    statuses: [
      { id: 1, label: 'Aperto', meta: { system_key: 'open', group: 'open', completion_percentage: 0, color: 'blue', icon: null } },
      { id: 2, label: 'Chiuso', meta: { system_key: 'closed_positive', group: 'closed_positive', completion_percentage: 100, color: 'green', icon: null } },
    ],
  })
})

describe('TaskKanbanView (spec 0157 D-2..D-5, spec 0164 D-1..D-3)', () => {
  it('renders one column per active status, in catalog order (per stato), each loading its own rows', () => {
    columnRowsMock.mockImplementation((args: { kanbanGroup: { by: string; key: number | string } }) =>
      args.kanbanGroup.by === 'status' && args.kanbanGroup.key === 1
        ? columnState([openRow(1)])
        : columnState([]),
    )

    renderView({ mode: 'status' })

    expect(screen.getByRole('heading', { name: 'Aperto' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Chiuso' })).toBeInTheDocument()
    expect(screen.getByText('Task 1')).toBeInTheDocument()
  })

  it('renders the seven fixed due-date buckets (per scadenza), including "Più avanti"', () => {
    renderView({ mode: 'due' })

    expect(screen.getByText('Scaduti')).toBeInTheDocument()
    expect(screen.getByText('Oggi')).toBeInTheDocument()
    expect(screen.getByText('Domani')).toBeInTheDocument()
    expect(screen.getByText('Questa settimana')).toBeInTheDocument()
    expect(screen.getByText('Questo mese')).toBeInTheDocument()
    expect(screen.getByText('Più avanti')).toBeInTheDocument()
    expect(screen.getByText('Completati')).toBeInTheDocument()
  })

  it('shows no 500-cap warning even when a column carries more than 500 rows (D-4, AC-006)', () => {
    columnRowsMock.mockImplementation((args: { kanbanGroup: { by: string; key: number | string } }) =>
      args.kanbanGroup.by === 'status' && args.kanbanGroup.key === 1
        ? { ...columnState([openRow(1)]), hasNextPage: true }
        : columnState([]),
    )

    renderView({ mode: 'status' })

    expect(screen.queryByText(/640/)).not.toBeInTheDocument()
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('the "+" opens the create form pre-filled with the status column (D-4)', () => {
    const onCreateTask = vi.fn()
    renderView({ mode: 'status', onCreateTask })

    // Only the manual (open/pending) status column offers "+"; "Chiuso" (closed_positive) never does.
    const addButtons = screen.getAllByRole('button', { name: 'Nuovo task' })
    expect(addButtons).toHaveLength(1)

    addButtons[0].click()
    expect(onCreateTask).toHaveBeenCalledWith({ task_status_id: 1 })
  })

  it('a successful status move invalidates only the origin and destination columns, nothing else (D-3, AC-005)', () => {
    const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    const invalidateSpy = vi.spyOn(queryClient, 'invalidateQueries')
    const moveToStatus = vi.fn()
    statusMoveMock.mockReturnValue({
      moveToStatus,
      completingTask: null,
      closeCompleteDialog: vi.fn(),
      handleCompleted: vi.fn(),
    })

    renderView({ mode: 'status' }, queryClient)

    capturedDnd.onDrop?.(openRow(1), '1', '2')
    expect(moveToStatus).toHaveBeenCalledWith(expect.objectContaining({ id: 1 }), 2, 'closed_positive', expect.any(Function))

    invalidateSpy.mockClear()
    const onMutated = moveToStatus.mock.calls[0]?.[3] as () => void
    onMutated()

    expect(invalidateSpy).toHaveBeenCalledWith({ queryKey: ['tasks', 'kanban', 'column', { by: 'status', key: 1 }] })
    expect(invalidateSpy).toHaveBeenCalledWith({ queryKey: ['tasks', 'kanban', 'column', { by: 'status', key: 2 }] })
    expect(invalidateSpy).toHaveBeenCalledTimes(2)
  })

  it('a move whose onMutated never runs (e.g. a cancelled "Completa") invalidates nothing (AC-005)', () => {
    const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    const invalidateSpy = vi.spyOn(queryClient, 'invalidateQueries')
    const moveToStatus = vi.fn()
    statusMoveMock.mockReturnValue({
      moveToStatus,
      completingTask: null,
      closeCompleteDialog: vi.fn(),
      handleCompleted: vi.fn(),
    })

    renderView({ mode: 'status' }, queryClient)

    invalidateSpy.mockClear()
    capturedDnd.onDrop?.(openRow(1), '1', '2')

    expect(invalidateSpy).not.toHaveBeenCalled()
  })
})
