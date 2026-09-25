import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { TaskQuickCreateRow } from '@/features/tasks/task-quick-create-row'
import { createTask } from '@/features/tasks/api'
import type { AsyncPaginatedSelectLabels } from '@/components/ui/async-paginated-select'
import type { AsyncPaginatedMultiSelectLabels } from '@/components/ui/async-paginated-multi-select'

/**
 * Spec 0156 D-7/AC-008: the quick-create row at the bottom of the tasks
 * list. Covers what the row itself owns — defaults, gating, submit/refresh
 * — not the async pickers' own network path (`async-paginated-*.test.tsx`).
 */

const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => canMock(permission), hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('@/features/auth/use-auth', () => ({
  useAuth: () => ({ user: { id: 99, name: 'Utente Corrente' } }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/tasks/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/tasks/api')>('@/features/tasks/api')
  return { ...actual, createTask: vi.fn() }
})

// 0154 D-8's predefined rows: only `task-types` carries one here, so the
// other two catalogs resolve to "no default" — exercising both branches of
// the fill-only-while-untouched effect in the same run.
vi.mock('@/features/for-select/api', () => ({
  fetchForSelect: vi.fn((resource: string) => {
    if (resource === 'task-types') {
      return Promise.resolve({ items: [{ id: 11, label: 'Chiamata', meta: { is_default: true } }], hasMore: false })
    }
    return Promise.resolve({ items: [], hasMore: false })
  }),
}))

// Simple stubs exposing both the CURRENT value (for asserting defaults) and
// a way to change it (for asserting the submit payload), mirroring the
// stubbing convention `task-board-bulk-bar.test.tsx` already uses.
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    labels,
    value,
    onChange,
  }: {
    labels: AsyncPaginatedSelectLabels
    value: number | null
    onChange: (value: number | null) => void
  }) => (
    <button type="button" onClick={() => onChange(777)}>
      {`${labels.triggerLabel}:${value ?? 'none'}`}
    </button>
  ),
}))
vi.mock('@/components/ui/async-paginated-multi-select', () => ({
  AsyncPaginatedMultiSelect: ({
    labels,
    value,
    onChange,
  }: {
    labels: AsyncPaginatedMultiSelectLabels
    value: number[]
    onChange: (value: number[]) => void
  }) => (
    <button type="button" onClick={() => onChange([...value, 888])}>
      {`${labels.triggerLabel}:${JSON.stringify(value)}`}
    </button>
  ),
}))

function renderRow(onCreated = vi.fn()) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <TaskQuickCreateRow onCreated={onCreated} />
    </QueryClientProvider>,
  )
  return { onCreated }
}

const label = (key: string) => i18n.t(key)

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
  canMock.mockReturnValue(true)
  vi.mocked(createTask).mockReset()
  vi.mocked(toast.success).mockReset()
  vi.mocked(toast.error).mockReset()
})

describe('TaskQuickCreateRow — visibility (AC-008)', () => {
  it('renders nothing without tasks.create', () => {
    canMock.mockReturnValue(false)
    renderRow()

    expect(screen.queryByRole('form', { name: label('tasks.quickCreate.formLabel') })).not.toBeInTheDocument()
    expect(screen.queryByLabelText(label('tasks.form.title'))).not.toBeInTheDocument()
  })

  it('renders the row with tasks.create', () => {
    renderRow()

    expect(screen.getByRole('form', { name: label('tasks.quickCreate.formLabel') })).toBeInTheDocument()
  })
})

describe('TaskQuickCreateRow — defaults (spec 0154 D-8, 0156 D-7)', () => {
  it('defaults requester and assignee to the connected actor, and the due date to today', async () => {
    renderRow()

    const today = new Date().toISOString().slice(0, 10)
    expect(screen.getByLabelText(label('tasks.form.endDate'))).toHaveValue(today)
    expect(await screen.findByRole('button', { name: `${label('tasks.form.requester')}:99` })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: `${label('tasks.form.assignees')}:[99]` })).toBeInTheDocument()
  })

  it('fills the type from the catalog default once resolved, without touching an untouched field only', async () => {
    renderRow()

    expect(await screen.findByRole('button', { name: `${label('tasks.form.type')}:11` })).toBeInTheDocument()
    // No default row for priority/importance in this fixture: stays unset.
    expect(screen.getByRole('button', { name: `${label('tasks.form.priority')}:none` })).toBeInTheDocument()
  })
})

describe('TaskQuickCreateRow — submit (AC-008)', () => {
  it('creates the task with the defaults and the typed title, then resets and refreshes', async () => {
    vi.mocked(createTask).mockResolvedValueOnce({ id: 501 } as never)
    const { onCreated } = renderRow()

    fireEvent.change(screen.getByLabelText(label('tasks.form.title')), {
      target: { value: 'Richiamare il fornitore' },
    })
    // Wait for the async type default to land before submitting, so the
    // payload assertion below is deterministic regardless of fetch timing.
    await screen.findByRole('button', { name: `${label('tasks.form.type')}:11` })

    fireEvent.click(screen.getByRole('button', { name: label('tasks.quickCreate.submit') }))

    await waitFor(() =>
      expect(createTask).toHaveBeenCalledWith(
        expect.objectContaining({
          title: 'Richiamare il fornitore',
          requester_id: 99,
          assignee_ids: [99],
          watcher_ids: [],
          task_type_id: 11,
        }),
      ),
    )
    await waitFor(() => expect(toast.success).toHaveBeenCalled())
    expect(onCreated).toHaveBeenCalledTimes(1)
    // The title resets to empty after a successful create (fresh row for the next one).
    await waitFor(() => expect(screen.getByLabelText(label('tasks.form.title'))).toHaveValue(''))
  })

  it('blocks the submit and shows the inline error when the title is empty', async () => {
    renderRow()

    fireEvent.click(screen.getByRole('button', { name: label('tasks.quickCreate.submit') }))

    expect(await screen.findByText(label('tasks.quickCreate.titleRequired'))).toBeInTheDocument()
    expect(createTask).not.toHaveBeenCalled()
  })

  it('shows a generic error toast and does not refresh on failure', async () => {
    vi.mocked(createTask).mockRejectedValueOnce(new Error('network'))
    const { onCreated } = renderRow()

    fireEvent.change(screen.getByLabelText(label('tasks.form.title')), { target: { value: 'Task' } })
    fireEvent.click(screen.getByRole('button', { name: label('tasks.quickCreate.submit') }))

    await waitFor(() => expect(toast.error).toHaveBeenCalled())
    expect(onCreated).not.toHaveBeenCalled()
  })
})
