import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { TooltipProvider } from '@/components/ui/tooltip'
import { workOrderStage } from '@/features/work-orders/task-board/task-board-fixtures'
import { TaskBoardStageGroup } from '@/features/work-orders/task-board/task-board-stage-group'
import { stageAccentAt } from '@/features/work-orders/task-board/task-board-stage-accent'
import type { BoardStageGroup } from '@/features/work-orders/task-board/task-board-filters'

interface MutateOptions {
  onSuccess?: (data?: unknown) => void
  onError?: (error: unknown) => void
}

const renameMutateMock = vi.fn<(vars: unknown, options?: MutateOptions) => void>()
const deleteMutateMock = vi.fn<(id: unknown, options?: MutateOptions) => void>()
const closeMutateMock = vi.fn<(id: unknown, options?: MutateOptions) => void>()
const reopenMutateMock = vi.fn<(id: unknown, options?: MutateOptions) => void>()

vi.mock('@/features/work-orders/task-board/use-task-board-mutations', () => ({
  useRenameWorkOrderStage: () => ({ mutate: renameMutateMock }),
  useDeleteWorkOrderStage: () => ({ mutate: deleteMutateMock }),
  useCloseWorkOrderStage: () => ({ mutate: closeMutateMock }),
  useReopenWorkOrderStage: () => ({ mutate: reopenMutateMock }),
}))

vi.mock('sonner', () => ({ toast: { error: vi.fn() } }))

function noop() {}

function renderGroup(group: BoardStageGroup, overrides: Partial<{ isReadOnly: boolean }> = {}) {
  return render(
    <TooltipProvider>
      <ul>
        <TaskBoardStageGroup
          workOrderId={9}
          group={group}
          accent={stageAccentAt(0)}
          isReadOnly={overrides.isReadOnly ?? false}
          isDragOver={false}
          today="2026-09-22"
          selectedTaskIds={new Set()}
          onToggleSelection={noop}
          onOpenTask={noop}
          onAddTask={noop}
        />
      </ul>
    </TooltipProvider>,
  )
}

function openStageMenu() {
  fireEvent.pointerDown(screen.getByRole('button', { name: 'Phase actions' }), { button: 0 })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  renameMutateMock.mockReset()
  deleteMutateMock.mockReset()
  closeMutateMock.mockReset()
  reopenMutateMock.mockReset()
  vi.mocked(toast.error).mockReset()
})

function fase(overrides: Parameters<typeof workOrderStage>[0] = {}): BoardStageGroup {
  return { stage: workOrderStage({ id: 1, name: 'Sopralluogo', sort_order: 0, ...overrides }), roots: [] }
}

describe('TaskBoardStageGroup — rename', () => {
  it('submits the trimmed name on Enter', async () => {
    renderGroup(fase())
    openStageMenu()
    fireEvent.click(screen.getByRole('menuitem', { name: 'Rename' }))

    // `startRename` defers opening the input by one macrotask (see its own
    // comment): Radix's FocusScope races an `autoFocus` opened in the same
    // tick as the menu's own close/focus-restore.
    const input = await waitFor(() => screen.getByDisplayValue('Sopralluogo'))
    fireEvent.change(input, { target: { value: '  Nuovo nome  ' } })
    fireEvent.keyDown(input, { key: 'Enter' })

    expect(renameMutateMock).toHaveBeenCalledWith({ stageId: 1, name: 'Nuovo nome' }, expect.anything())
  })

  it('cancels on Escape without calling the mutation', async () => {
    renderGroup(fase())
    openStageMenu()
    fireEvent.click(screen.getByRole('menuitem', { name: 'Rename' }))

    const input = await waitFor(() => screen.getByDisplayValue('Sopralluogo'))
    fireEvent.keyDown(input, { key: 'Escape' })

    expect(renameMutateMock).not.toHaveBeenCalled()
    expect(screen.queryByDisplayValue('Sopralluogo')).not.toBeInTheDocument()
  })
})

describe('TaskBoardStageGroup — close (AC-008)', () => {
  it('shows the open-tasks-count toast on a 409 conflict', () => {
    closeMutateMock.mockImplementation((_id, options) => {
      options?.onError?.({ response: { data: { success: false, message: 'conflict', data: { open_tasks_count: 3 } } } })
    })

    renderGroup(fase())
    openStageMenu()
    fireEvent.click(screen.getByRole('menuitem', { name: 'Close phase' }))

    expect(closeMutateMock).toHaveBeenCalledWith(1, expect.anything())
    expect(toast.error).toHaveBeenCalledWith('3 tasks in this phase are still open.')
  })

  it('falls back to the generic error message without an open_tasks_count', () => {
    closeMutateMock.mockImplementation((_id, options) => {
      options?.onError?.({ response: { data: { success: false, message: 'error' } } })
    })

    renderGroup(fase())
    openStageMenu()
    fireEvent.click(screen.getByRole('menuitem', { name: 'Close phase' }))

    expect(toast.error).toHaveBeenCalledWith('Something went wrong. Please try again.')
  })
})

describe('TaskBoardStageGroup — delete (AC-005)', () => {
  it('asks for confirmation, then deletes on confirm', () => {
    deleteMutateMock.mockImplementation((_id, options) => options?.onSuccess?.())

    renderGroup(fase())
    openStageMenu()
    fireEvent.click(screen.getByRole('menuitem', { name: 'Delete phase' }))

    expect(screen.getByRole('alertdialog')).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Delete phase' }))

    expect(deleteMutateMock).toHaveBeenCalledWith(1, expect.anything())
  })
})

describe('TaskBoardStageGroup — "Senza fase" and read-only (AC-029)', () => {
  it('renders no drag handle, no menu and no "+ Task" for "Senza fase" when read-only', () => {
    renderGroup({ stage: null, roots: [] }, { isReadOnly: true })

    expect(screen.getByText('No phase')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Task' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Phase actions' })).not.toBeInTheDocument()
  })

  it('calls onAddTask with the fase id for an open stage', () => {
    const onAddTask = vi.fn()
    render(
      <TooltipProvider>
        <ul>
          <TaskBoardStageGroup
            workOrderId={9}
            group={fase()}
            accent={stageAccentAt(0)}
            isReadOnly={false}
            isDragOver={false}
            today="2026-09-22"
            selectedTaskIds={new Set()}
            onToggleSelection={noop}
            onOpenTask={noop}
            onAddTask={onAddTask}
          />
        </ul>
      </TooltipProvider>,
    )

    fireEvent.click(screen.getByRole('button', { name: 'Task' }))
    expect(onAddTask).toHaveBeenCalledWith(1)
  })
})
