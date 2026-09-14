import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { TaskDetailScreen } from '@/features/tasks/task-screens'
import { fetchTask, taskDetailQueryKey } from '@/features/tasks/api'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import { taskDetail } from '@/features/tasks/task-fixtures'

/**
 * "Crea sotto-task" always opens the create form in a Sheet above the parent,
 * whatever the actor's open-mode preference; opening an existing child keeps
 * honoring that preference. `useModuleOpener` and the presentational view are
 * stubbed: this suite covers only the wiring `TaskDetailScreen` owns.
 */
const TASK_ID = 7

interface OpenerStub {
  options: { forceMode?: string } | undefined
  openCreateWith: ReturnType<typeof vi.fn>
  openView: ReturnType<typeof vi.fn>
}

const openers: OpenerStub[] = []
vi.mock('@/features/modules/use-module-opener', () => ({
  useModuleOpener: (_domain: string, options?: { forceMode?: string }) => {
    const stub: OpenerStub = { options, openCreateWith: vi.fn(), openView: vi.fn() }
    openers.push(stub)
    return {
      openCreate: vi.fn(),
      openCreateWith: stub.openCreateWith,
      openView: stub.openView,
      openEdit: vi.fn(),
      openDuplicate: vi.fn(),
      sheet: options?.forceMode === undefined ? null : <div>subtask-sheet</div>,
    }
  },
}))

vi.mock('@/features/tasks/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/tasks/api')>('@/features/tasks/api')
  return { ...actual, fetchTask: vi.fn(() => Promise.resolve(taskDetail({ id: TASK_ID }))) }
})

vi.mock('@/features/tasks/task-detail', () => ({
  TaskDetailView: ({
    onCreateSubtask,
    onOpenSubtask,
  }: {
    onCreateSubtask: () => void
    onOpenSubtask: (id: number) => void
  }) => (
    <>
      <button type="button" onClick={onCreateSubtask}>
        create-subtask
      </button>
      <button type="button" onClick={() => onOpenSubtask(99)}>
        open-subtask
      </button>
    </>
  ),
}))

function renderScreen(): QueryClient {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={queryClient}>
      <TaskDetailScreen id={TASK_ID} onEdit={vi.fn()} />
    </QueryClientProvider>,
  )
  return queryClient
}

function lastOpeners(): { preference: OpenerStub; forced: OpenerStub } {
  const preference = openers.findLast((stub) => stub.options?.forceMode === undefined)
  const forced = openers.findLast((stub) => stub.options?.forceMode === OPEN_MODE_MODAL)
  if (!preference || !forced) {
    throw new Error('expected both openers to be mounted')
  }
  return { preference, forced }
}

describe('TaskDetailScreen', () => {
  beforeEach(() => {
    openers.length = 0
  })

  it('opens the subtask create form through the modal-forced opener with the parent id', async () => {
    renderScreen()

    fireEvent.click(await screen.findByRole('button', { name: 'create-subtask' }))

    const { preference, forced } = lastOpeners()
    expect(forced.openCreateWith).toHaveBeenCalledWith({ parent_task_id: TASK_ID })
    expect(preference.openCreateWith).not.toHaveBeenCalled()
  })

  it('opens an existing subtask through the preference-driven opener', async () => {
    renderScreen()

    fireEvent.click(await screen.findByRole('button', { name: 'open-subtask' }))

    const { preference, forced } = lastOpeners()
    expect(preference.openView).toHaveBeenCalledWith({ id: 99, actions: [] })
    expect(forced.openView).not.toHaveBeenCalled()
  })

  // The subtask form reads the parent through the SAME detail query key: its
  // mount refetches it. Unmounting the sheet during that refetch remounted the
  // form, which refetched again — an endless reload loop.
  it('keeps the subtask sheet mounted while the parent detail refetches', async () => {
    const queryClient = renderScreen()
    await screen.findByRole('button', { name: 'create-subtask' })
    vi.mocked(fetchTask).mockImplementationOnce(() => new Promise(() => {}))

    void queryClient.refetchQueries({ queryKey: taskDetailQueryKey(TASK_ID) })

    await waitFor(() => expect(screen.queryByRole('button', { name: 'create-subtask' })).not.toBeInTheDocument())
    expect(screen.getByText('subtask-sheet')).toBeInTheDocument()
  })
})
