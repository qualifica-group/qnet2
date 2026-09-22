import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { createWorkOrderStage, fetchTaskBoard } from '@/features/work-orders/task-board/api'
import { taskBoardPayload, workOrderStage } from '@/features/work-orders/task-board/task-board-fixtures'
import type { BoardStageGroup } from '@/features/work-orders/task-board/task-board-filters'
import { WorkOrderTaskBoard } from '@/features/work-orders/task-board/work-order-task-board'

vi.mock('@/features/work-orders/task-board/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/work-orders/task-board/api')>()),
  fetchTaskBoard: vi.fn(),
  createWorkOrderStage: vi.fn(),
}))

vi.mock('@/features/auth/use-auth', () => ({ useAuth: () => ({ user: { id: 1 } }) }))
vi.mock('@/features/auth/use-abilities', () => ({ useAbilities: () => ({ can: () => true }) }))
vi.mock('@/features/modules/use-module-opener', () => ({
  useModuleOpener: () => ({ openCreateWith: vi.fn(), openView: vi.fn(), sheet: null }),
}))

// The views, toolbar, KPIs and bulk bar have their own tests: here only the
// header menu, the create dialog and the empty-state branching are under test.
vi.mock('@/features/work-orders/task-board/task-board-list-view', () => ({
  TaskBoardListView: ({ groups }: { groups: BoardStageGroup[] }) => (
    <ul aria-label="stage groups">
      {groups.map((group) => (
        <li key={group.stage?.id ?? 'none'}>{group.stage?.name ?? 'no-stage'}</li>
      ))}
    </ul>
  ),
}))
vi.mock('@/features/work-orders/task-board/task-board-kanban-view', () => ({ TaskBoardKanbanView: () => null }))
vi.mock('@/features/work-orders/task-board/task-board-toolbar', () => ({ TaskBoardToolbar: () => null }))
vi.mock('@/features/work-orders/task-board/task-board-kpis', () => ({ TaskBoardKpis: () => null }))
vi.mock('@/features/work-orders/task-board/task-board-bulk-bar', () => ({ TaskBoardBulkBar: () => null }))

const fetchTaskBoardMock = vi.mocked(fetchTaskBoard)
const createWorkOrderStageMock = vi.mocked(createWorkOrderStage)

function renderBoard() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <WorkOrderTaskBoard workOrderId={20} />
    </QueryClientProvider>,
  )
}

async function openCreateDialog() {
  const trigger = await screen.findByRole('button', { name: 'More actions' })
  fireEvent.pointerDown(trigger, { button: 0, ctrlKey: false })
  fireEvent.click(await screen.findByRole('menuitem', { name: 'New phase' }))
  return screen.findByRole('dialog', { name: 'New phase' })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchTaskBoardMock.mockReset()
  createWorkOrderStageMock.mockReset()
})

describe('WorkOrderTaskBoard — header "..." menu', () => {
  it('creates a phase with the name typed in the dialog', async () => {
    fetchTaskBoardMock.mockResolvedValue(taskBoardPayload({ stages: [], tasks: [] }))
    createWorkOrderStageMock.mockResolvedValue(workOrderStage({ id: 5, name: 'Collaudo' }))
    renderBoard()

    await openCreateDialog()
    fireEvent.change(screen.getByLabelText('Phase name'), { target: { value: 'Collaudo' } })
    fireEvent.click(screen.getByRole('button', { name: 'Create phase' }))

    await waitFor(() => expect(createWorkOrderStageMock).toHaveBeenCalledWith(20, 'Collaudo'))
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
  })

  it('refuses an empty name without calling the server', async () => {
    fetchTaskBoardMock.mockResolvedValue(taskBoardPayload({ stages: [], tasks: [] }))
    renderBoard()

    await openCreateDialog()
    fireEvent.click(screen.getByRole('button', { name: 'Create phase' }))

    expect(await screen.findByText('Enter the phase name.')).toBeInTheDocument()
    expect(createWorkOrderStageMock).not.toHaveBeenCalled()
  })

  it('hides the menu when the board is read-only', async () => {
    fetchTaskBoardMock.mockResolvedValue(taskBoardPayload({ stages: [], tasks: [], is_read_only: true }))
    renderBoard()

    expect(await screen.findByRole('status')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'More actions' })).not.toBeInTheDocument()
  })
})

describe('WorkOrderTaskBoard — phases without tasks', () => {
  it('shows the phases even when the work order has no task yet', async () => {
    fetchTaskBoardMock.mockResolvedValue(
      taskBoardPayload({ stages: [workOrderStage({ id: 1, name: 'Sopralluogo', sort_order: 0 })], tasks: [] }),
    )
    renderBoard()

    expect(await screen.findByText('Sopralluogo')).toBeInTheDocument()
  })
})
