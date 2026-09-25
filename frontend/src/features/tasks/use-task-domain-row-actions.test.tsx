import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { useTaskDomainRowActions } from '@/features/tasks/use-task-domain-row-actions'
import { blockTask, fetchTask, uncompleteTask, unblockTask } from '@/features/tasks/api'
import { taskDetailWithPermissions } from '@/features/tasks/task-fixtures'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * Spec 0156 D-5: the six domain-action row keys, exercised through the hook
 * that wires them into the tasks list. `complete`/`approve`/`reject`/
 * `request_update` fetch the full task first and open the SAME dialog the
 * detail's `TaskActionsBar` uses (`task-complete-dialog.test.tsx` already
 * covers the dialog's own submit wiring in depth — this suite is only about
 * the row-action routing: which key fetches + opens what, and which runs
 * directly).
 */

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/tasks/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/tasks/api')>('@/features/tasks/api')
  return { ...actual, fetchTask: vi.fn(), uncompleteTask: vi.fn(), blockTask: vi.fn(), unblockTask: vi.fn() }
})

// The dialogs `complete`/`request_update` mount reach for async pickers;
// stubbed to plain triggers so this suite exercises the row-action routing,
// not the for-select network path (mirrors `task-complete-dialog.test.tsx`).
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({ labels, onChange }: { labels: { triggerLabel: string }; onChange: (value: number | null) => void }) => (
    <button type="button" onClick={() => onChange(50)}>
      {labels.triggerLabel}
    </button>
  ),
}))
vi.mock('@/features/time-entries/form/time-entry-type-picker', () => ({
  useTimeEntryTypeOptions: () => ({
    options: [{ id: 2, label: 'Attività', meta: { color: 'blue', icon: 'clipboard-list' } }],
    isPending: false,
    isError: false,
    refetch: vi.fn(),
  }),
  TimeEntryTypePicker: ({ value }: { value: number | null }) => <div>{value ?? ''}</div>,
}))

function action(key: string): TableActionDefinition {
  return { key, label: `tasks.actions.${key}.label`, icon: 'eye', type: 'action', confirm: false }
}

const ROW: TableRow = { id: 90, actions: [] }

function Harness() {
  const { handleAction, dialogSlot } = useTaskDomainRowActions({ onMutated })
  return (
    <>
      <button type="button" onClick={() => handleAction(action('complete'), ROW)}>
        trigger-complete
      </button>
      <button type="button" onClick={() => handleAction(action('uncomplete'), ROW)}>
        trigger-uncomplete
      </button>
      <button type="button" onClick={() => handleAction(action('block'), ROW)}>
        trigger-block
      </button>
      <button type="button" onClick={() => handleAction(action('unblock'), ROW)}>
        trigger-unblock
      </button>
      {dialogSlot}
    </>
  )
}

const onMutated = vi.fn()

function renderHarness() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <Harness />
    </QueryClientProvider>,
  )
}

const label = (key: string) => i18n.t(key)

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  onMutated.mockReset()
  vi.mocked(fetchTask).mockReset()
  vi.mocked(uncompleteTask).mockReset()
  vi.mocked(blockTask).mockReset()
  vi.mocked(unblockTask).mockReset()
  vi.mocked(toast.success).mockReset()
  vi.mocked(toast.error).mockReset()
})

describe('useTaskDomainRowActions — "complete" (fetch detail + dialog)', () => {
  it('fetches the full task and opens the Completa dialog on it', async () => {
    vi.mocked(fetchTask).mockResolvedValueOnce(
      taskDetailWithPermissions({ id: 90, requires_closure_feedback: false }),
    )
    renderHarness()

    fireEvent.click(screen.getByRole('button', { name: 'trigger-complete' }))

    await waitFor(() => expect(fetchTask).toHaveBeenCalledWith(90))
    expect(
      await screen.findByRole('heading', { name: label('tasks.actions.complete.label') }),
    ).toBeInTheDocument()
  })

  it('shows a toast and never opens the dialog when the fetch fails', async () => {
    vi.mocked(fetchTask).mockRejectedValueOnce(new Error('network'))
    renderHarness()

    fireEvent.click(screen.getByRole('button', { name: 'trigger-complete' }))

    await waitFor(() => expect(toast.error).toHaveBeenCalled())
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })
})

describe('useTaskDomainRowActions — "uncomplete" (direct call, no dialog)', () => {
  it('calls uncompleteTask directly and reports success without ever fetching the detail', async () => {
    vi.mocked(uncompleteTask).mockResolvedValueOnce(taskDetailWithPermissions())
    renderHarness()

    fireEvent.click(screen.getByRole('button', { name: 'trigger-uncomplete' }))

    await waitFor(() => expect(uncompleteTask).toHaveBeenCalledWith(90))
    await waitFor(() => expect(toast.success).toHaveBeenCalled())
    expect(onMutated).toHaveBeenCalledTimes(1)
    expect(fetchTask).not.toHaveBeenCalled()
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('shows a toast and does not report success on failure', async () => {
    vi.mocked(uncompleteTask).mockRejectedValueOnce(new Error('network'))
    renderHarness()

    fireEvent.click(screen.getByRole('button', { name: 'trigger-uncomplete' }))

    await waitFor(() => expect(toast.error).toHaveBeenCalled())
    expect(onMutated).not.toHaveBeenCalled()
  })
})

describe('useTaskDomainRowActions — block/unblock (direct call)', () => {
  it('block: calls blockTask directly and refreshes on success', async () => {
    vi.mocked(blockTask).mockResolvedValueOnce(taskDetailWithPermissions())
    renderHarness()

    fireEvent.click(screen.getByRole('button', { name: 'trigger-block' }))

    await waitFor(() => expect(blockTask).toHaveBeenCalledWith(90))
    expect(onMutated).toHaveBeenCalledTimes(1)
  })

  it('unblock: calls unblockTask directly and refreshes on success', async () => {
    vi.mocked(unblockTask).mockResolvedValueOnce(taskDetailWithPermissions())
    renderHarness()

    fireEvent.click(screen.getByRole('button', { name: 'trigger-unblock' }))

    await waitFor(() => expect(unblockTask).toHaveBeenCalledWith(90))
    expect(onMutated).toHaveBeenCalledTimes(1)
  })
})
