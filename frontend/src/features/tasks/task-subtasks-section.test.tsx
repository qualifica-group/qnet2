import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { ConfirmContext, type ConfirmFn } from '@/components/confirm-dialog-context'
import { TaskSubtasksSection } from '@/features/tasks/task-subtasks-section'
import { deleteTask, fetchTask, reorderTaskSubtasks, uncompleteTask } from '@/features/tasks/api'
import { NO_TASK_ACTIONS, taskDetailWithPermissions, taskSubtask } from '@/features/tasks/task-fixtures'
import type { TaskSubtask } from '@/features/tasks/types'

/** Abilities granted to the actor under test; rewritten per test. */
let granted: string[] = []

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({
    can: (permission: string) => granted.includes(permission),
    hasRole: () => false,
    roles: [],
    isLoading: false,
  }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/tasks/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/tasks/api')>('@/features/tasks/api')
  return {
    ...actual,
    reorderTaskSubtasks: vi.fn(),
    uncompleteTask: vi.fn(),
    deleteTask: vi.fn(),
    fetchTask: vi.fn(),
  }
})

// The full completion flow (feedback/validation/segnatempo) is already
// covered by `task-complete-dialog.test.tsx`; this suite only checks that the
// panel opens it with the right task and `forAllAssignees={false}` (D-6).
vi.mock('@/features/tasks/task-complete-dialog', () => ({
  TaskCompleteDialog: ({
    open,
    task,
    forAllAssignees,
  }: {
    open: boolean
    task: { id: number }
    forAllAssignees: boolean
  }) => (open ? <p>{`complete-dialog:${task.id}:${String(forAllAssignees)}`}</p> : null),
}))

const label = (key: string) => i18n.t(key)

const ROW_HEIGHT = 40

/**
 * @dnd-kit's keyboard coordinate getter picks the next target by comparing
 * `getBoundingClientRect()` of every row. jsdom always returns an all-zero
 * rect, so every row would tie; this stubs a rect whose `top` follows DOM
 * order (mirrors `sortable-list.test.tsx`).
 */
function mockRowRects() {
  vi.spyOn(HTMLElement.prototype, 'getBoundingClientRect').mockImplementation(function (
    this: HTMLElement,
  ) {
    const rows = Array.from(document.querySelectorAll('li'))
    const index = rows.indexOf(this as HTMLLIElement)
    const top = index === -1 ? 0 : index * ROW_HEIGHT

    return {
      width: 280,
      height: ROW_HEIGHT,
      top,
      bottom: top + ROW_HEIGHT,
      left: 0,
      right: 280,
      x: 0,
      y: top,
      toJSON: () => ({}),
    } as DOMRect
  })
}

/** The keyboard sensor attaches its move/drop listeners in a macrotask. */
async function flushSensorAttach() {
  await new Promise((resolve) => setTimeout(resolve, 0))
}

function renderSection(
  subtasks: TaskSubtask[],
  overrides: { canCreateSubtask?: boolean; canReorder?: boolean } = {},
  confirmImpl: ConfirmFn = () => Promise.resolve(true),
) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const onOpen = vi.fn()
  const onCreate = vi.fn()
  render(
    <QueryClientProvider client={client}>
      <ConfirmContext.Provider value={confirmImpl}>
        <TaskSubtasksSection
          parentTaskId={90}
          subtasks={subtasks}
          onOpen={onOpen}
          onCreate={onCreate}
          canCreateSubtask={overrides.canCreateSubtask ?? true}
          canReorder={overrides.canReorder ?? true}
        />
      </ConfirmContext.Provider>
    </QueryClientProvider>,
  )
  return { onOpen, onCreate, client }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  granted = ['tasks.view', 'tasks.create']
  vi.mocked(reorderTaskSubtasks).mockReset()
  vi.mocked(uncompleteTask).mockReset()
  vi.mocked(deleteTask).mockReset()
  vi.mocked(fetchTask).mockReset()
  vi.mocked(toast.success).mockReset()
  vi.mocked(toast.error).mockReset()
})

describe('TaskSubtasksSection — listing (AC-085)', () => {
  it('lists every child already carried by the parent detail', () => {
    renderSection([taskSubtask(), taskSubtask({ id: 102, title: 'Inviare la conferma' })])

    expect(screen.getByRole('button', { name: 'Preparare il preventivo' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Inviare la conferma' })).toBeInTheDocument()
  })

  it('shows an empty state rather than an empty list', () => {
    renderSection([])

    expect(screen.getByText(label('tasks.detail.subtasksEmpty'))).toBeInTheDocument()
  })

  it('renders the child status badge and its own derived percentage', () => {
    renderSection([taskSubtask({ completion_percentage: 75 })])

    expect(
      screen.getByRole('progressbar', { name: label('tasks.detail.completionPercentage') }),
    ).toHaveAttribute('aria-valuenow', '75')
  })

  it('opens the child detail by its own id', () => {
    const { onOpen } = renderSection([taskSubtask({ id: 102 })])

    fireEvent.click(screen.getByRole('button', { name: 'Preparare il preventivo' }))

    expect(onOpen).toHaveBeenCalledWith(102)
  })

  it('offers "crea sotto-task" and delegates the prefill to the caller, hides it when create_subtask is false', () => {
    const { onCreate } = renderSection([], { canCreateSubtask: true })
    fireEvent.click(screen.getByRole('button', { name: label('tasks.detail.createSubtask') }))
    expect(onCreate).toHaveBeenCalledTimes(1)

    renderSection([], { canCreateSubtask: false })
    expect(screen.queryAllByRole('button', { name: label('tasks.detail.createSubtask') })).toHaveLength(1)
  })
})

/** Spec 0155 D-4/AC-006/AC-007: the drag handle is the reorder affordance, gated on `update` on the PARENT. */
describe('TaskSubtasksSection — reorder (AC-006/AC-007)', () => {
  // Resolved lazily inside each `it` (not hoisted to the `describe` body):
  // `beforeAll` switches the active language AFTER `describe` bodies run.
  const handleLabel = () => label('tasks.detail.subtaskPanel.reorderHandle')

  beforeEach(() => {
    mockRowRects()
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('renders a drag handle per row when the actor may reorder', () => {
    renderSection([taskSubtask({ id: 101 }), taskSubtask({ id: 102 })], { canReorder: true })

    expect(screen.getAllByRole('button', { name: handleLabel() })).toHaveLength(2)
  })

  it('renders no drag handle when the actor may not reorder', () => {
    renderSection([taskSubtask({ id: 101 }), taskSubtask({ id: 102 })], { canReorder: false })

    expect(screen.queryByRole('button', { name: handleLabel() })).not.toBeInTheDocument()
  })

  it('calls the reorder endpoint with the parent id and the new order', async () => {
    vi.mocked(reorderTaskSubtasks).mockResolvedValueOnce([])
    renderSection([taskSubtask({ id: 101 }), taskSubtask({ id: 102 })], { canReorder: true })

    const [firstHandle] = screen.getAllByRole('button', { name: handleLabel() })
    firstHandle.focus()
    fireEvent.keyDown(firstHandle, { code: 'Space' })
    await flushSensorAttach()
    fireEvent.keyDown(document, { code: 'ArrowDown' })
    fireEvent.keyDown(document, { code: 'Space' })

    await waitFor(() => expect(reorderTaskSubtasks).toHaveBeenCalledWith(90, [102, 101]))
  })

  it('shows an error toast when the reorder request fails', async () => {
    vi.mocked(reorderTaskSubtasks).mockRejectedValueOnce(new Error('failed'))
    renderSection([taskSubtask({ id: 101 }), taskSubtask({ id: 102 })], { canReorder: true })

    const [firstHandle] = screen.getAllByRole('button', { name: handleLabel() })
    firstHandle.focus()
    fireEvent.keyDown(firstHandle, { code: 'Space' })
    await flushSensorAttach()
    fireEvent.keyDown(document, { code: 'ArrowDown' })
    fireEvent.keyDown(document, { code: 'Space' })

    await waitFor(() => expect(toast.error).toHaveBeenCalledWith(label('tasks.detail.subtaskPanel.reorderError')))
  })
})

function deletableSubtask(id: number) {
  const subtask = taskSubtask({ id })
  return { ...subtask, permissions: { actions: { ...subtask.permissions.actions, delete: true } } }
}

/** Spec 0155 D-5/AC-007: each row's own `permissions.actions` gates complete/reopen/delete. */
describe('TaskSubtasksSection — row actions (AC-007)', () => {
  it('shows Completa only when the child permits it, and opens the dialog with forAllAssignees=false', async () => {
    vi.mocked(fetchTask).mockResolvedValueOnce(taskDetailWithPermissions({ id: 101 }))
    renderSection([
      taskSubtask({ id: 101, permissions: { actions: { ...NO_TASK_ACTIONS, complete: true, delete: false } } }),
    ])

    expect(screen.getByRole('button', { name: label('tasks.detail.subtaskPanel.complete') })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: label('tasks.detail.subtaskPanel.reopen') })).not.toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: label('tasks.detail.subtaskPanel.complete') }))

    expect(await screen.findByText('complete-dialog:101:false')).toBeInTheDocument()
  })

  it('shows Riapri only when the child permits it; confirming reopens and refreshes the parent', async () => {
    vi.mocked(uncompleteTask).mockResolvedValueOnce(taskDetailWithPermissions())
    renderSection([
      taskSubtask({ id: 101, permissions: { actions: { ...NO_TASK_ACTIONS, uncomplete: true, delete: false } } }),
    ])

    expect(screen.queryByRole('button', { name: label('tasks.detail.subtaskPanel.complete') })).not.toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: label('tasks.detail.subtaskPanel.reopen') }))

    await waitFor(() => expect(uncompleteTask).toHaveBeenCalledWith(101))
  })

  it('hides delete when the child may not be deleted, deletes and toasts on confirm otherwise', async () => {
    vi.mocked(deleteTask).mockResolvedValueOnce(undefined)
    renderSection([taskSubtask({ id: 101 })])
    expect(screen.queryByRole('button', { name: label('tasks.detail.subtaskPanel.delete') })).not.toBeInTheDocument()

    renderSection([deletableSubtask(101)])
    fireEvent.click(screen.getAllByRole('button', { name: label('tasks.detail.subtaskPanel.delete') })[0])

    await waitFor(() => expect(deleteTask).toHaveBeenCalledWith(101))
    expect(toast.success).toHaveBeenCalledWith(label('tasks.detail.subtaskPanel.deleteSuccess'))
  })

  it('does not delete when the confirmation is declined', async () => {
    renderSection([deletableSubtask(101)], {}, () => Promise.resolve(false))

    fireEvent.click(screen.getByRole('button', { name: label('tasks.detail.subtaskPanel.delete') }))

    await new Promise((resolve) => setTimeout(resolve, 0))
    expect(deleteTask).not.toHaveBeenCalled()
  })
})
