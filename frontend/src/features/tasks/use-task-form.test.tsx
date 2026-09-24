import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { act, renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { useTaskForm } from '@/features/tasks/use-task-form'
import { taskDetailQueryKey } from '@/features/tasks/api'
import { workOrderDetailQueryKey } from '@/features/work-orders/api'
import { taskDetailWithPermissions, taskStatus } from '@/features/tasks/task-fixtures'
import { DEFAULT_MODULE_OPEN_PREFERENCES } from '@/features/modules/types'
import type { TaskStatusForSelectItem } from '@/features/tasks/for-select-api'
import type { TaskDetailWithPermissions } from '@/features/tasks/types'
import type { User } from '@/features/auth/types'

const fetchTaskMock = vi.fn<(id: number) => Promise<TaskDetailWithPermissions>>()
vi.mock('@/features/tasks/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/tasks/api')>('@/features/tasks/api')
  return { ...actual, createTask: vi.fn(), updateTask: vi.fn(), fetchTask: (id: number) => fetchTaskMock(id) }
})

const fetchWorkOrderMock = vi.fn()
vi.mock('@/features/work-orders/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/work-orders/api')>('@/features/work-orders/api')
  return { ...actual, fetchWorkOrder: (id: number) => fetchWorkOrderMock(id) }
})

/** The connected actor `useTaskForm` reads to prefill the requester (spec 0118 D-1). */
const currentUserMock = vi.fn<() => User | null>(() => currentUser())
vi.mock('@/features/auth/use-auth', () => ({
  useAuth: () => ({ user: currentUserMock() }),
}))

function currentUser(overrides: Partial<User> = {}): User {
  return {
    id: 99,
    name: 'Utente Corrente',
    email: 'utente@example.com',
    locale: 'en',
    roles: [],
    avatar_url: null,
    personal_data: null,
    created_at: null,
    module_open_preferences: DEFAULT_MODULE_OPEN_PREFERENCES,
    ui_scale: 40,
    date_format: 'dmy',
    time_format: '24h',
    color_preset: 'default',
    ...overrides,
  }
}

/** A stable `QueryClient` per test, never per render: a per-render one resets the cache and flakes. */
function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

/** One option of `GET /api/task-statuses/for-select`, with the presentation bag the form reads. */
function statusOption(
  overrides: Partial<TaskStatusForSelectItem['meta']> = {},
): TaskStatusForSelectItem {
  return {
    id: 6,
    label: 'Completato',
    meta: {
      system_key: 'closed_positive',
      group: 'closed_positive',
      completion_percentage: 100,
      color: 'green',
      icon: null,
      ...overrides,
    },
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchWorkOrderMock.mockReset()
  fetchTaskMock.mockReset()
  fetchTaskMock.mockResolvedValue(taskDetailWithPermissions())
})

/**
 * AC-081: the referent is anagrafica-scoped, so changing the anagrafica must
 * clear it — in the change HANDLER, never in an effect that could race a
 * later edit.
 */
describe('useTaskForm — anagrafica drives referente (AC-081)', () => {
  it('clears the selected referent when the anagrafica changes', () => {
    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'create' }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    act(() => {
      result.current.form.setValue('registry_id', 7)
      result.current.form.setValue('referent_id', 11)
    })
    expect(result.current.form.getValues('referent_id')).toBe(11)

    act(() => {
      result.current.form.setValue('registry_id', 8)
      result.current.handleRegistryChange(8)
    })

    expect(result.current.form.getValues('referent_id')).toBeNull()
    expect(result.current.form.getValues('registry_id')).toBe(8)
  })

  it('leaves the form valid after the reset: the referent is optional', async () => {
    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'create' }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    act(() => {
      result.current.form.setValue('title', 'Richiamare il cliente')
      result.current.form.setValue('task_status_id', 3)
      result.current.form.setValue('referent_id', 11)
      result.current.handleRegistryChange(null)
    })

    await act(async () => {
      await result.current.form.trigger()
    })

    // `getFieldState`, not `formState.errors`: RHF's formState is a Proxy that
    // only populates the keys a RENDER subscribed to, so reading `.errors`
    // outside one is vacuously empty and would make this assertion pass
    // whatever happened.
    expect(result.current.form.getFieldState('referent_id').error).toBeUndefined()
  })
})

/**
 * AC-084: the percentage is a projection of the picked status. It updates on
 * a pick, and it is NEVER a form value, so it can never be submitted (D-6).
 */
describe('useTaskForm — derived completion percentage (AC-084)', () => {
  it('is unset on a fresh create until a status is picked', () => {
    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'create' }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    expect(result.current.completionPercentage).toBeNull()
  })

  it('is seeded from the persisted status in edit mode', () => {
    const task = taskDetailWithPermissions()
    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'edit', task }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    expect(result.current.completionPercentage).toBe(25)
  })

  it('re-derives on picking another status, without writing anything to the form', () => {
    const task = taskDetailWithPermissions()
    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'edit', task }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    act(() => {
      result.current.handleStatusItemChange(statusOption())
    })

    expect(result.current.completionPercentage).toBe(100)
    expect(result.current.form.getValues()).not.toHaveProperty('completion_percentage')
  })

  /**
   * An ORDINARY status (`system_key: null`) still arrives fully projected: the
   * absent system key does not stop the percentage from being read off it.
   * (Spec 0121 D-7 retired the `statusGroup`/phase half of this test: the
   * closure-feedback rule that used to branch on the picked status' phase is
   * gone along with `closure_feedback` itself, so `useTaskForm` no longer
   * exposes `statusGroup` — nothing consumes it any more.)
   */
  it('reads the percentage of an ORDINARY status, not just a system one', () => {
    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'create' }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    act(() => {
      result.current.handleStatusItemChange(
        statusOption({ system_key: null, group: 'pending', completion_percentage: 40 }),
      )
    })

    expect(result.current.completionPercentage).toBe(40)
  })

  it('falls back to unset when the picker projects no meta, rather than showing a wrong value', () => {
    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'create' }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    act(() => {
      result.current.handleStatusItemChange({ id: 4, label: 'Stato senza meta' })
    })

    expect(result.current.completionPercentage).toBeNull()
  })
})

/** Spec 0118 D-1: `requester_id` is required now, and the actor is the requester most of the time. */
describe('useTaskForm — requester prefill on create (spec 0118 D-1)', () => {
  it('defaults the requester to the connected actor, editable', () => {
    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'create' }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    expect(result.current.form.getValues('requester_id')).toBe(99)
    expect(result.current.currentUserRef).toEqual({ id: 99, name: 'Utente Corrente' })

    act(() => {
      result.current.form.setValue('requester_id', 7)
    })
    expect(result.current.form.getValues('requester_id')).toBe(7)
  })

  it('leaves the requester null when no actor is resolved yet', () => {
    // `mockReturnValue` (not `-Once`): the hook may read `useAuth()` more than
    // once per mount, and a `-Once` override would only cover the first call.
    currentUserMock.mockReturnValue(null)
    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'create' }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    expect(result.current.form.getValues('requester_id')).toBeNull()
    expect(result.current.currentUserRef).toBeNull()

    currentUserMock.mockImplementation(() => currentUser())
  })

  it('does not touch the persisted requester in edit mode', () => {
    const task = taskDetailWithPermissions({ requester_id: 21, requester: { id: 21, name: 'Bruno Bianchi' } })
    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'edit', task }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    expect(result.current.form.getValues('requester_id')).toBe(21)
  })
})

/**
 * Spec 0133 D-4 (AC-012): "New task" from the Commessa detail's Task tab seeds
 * `work_order_id` and hydrates the picker label from the work order detail
 * already cached underneath — no second request.
 */
describe('useTaskForm — work order prefill on create (spec 0133)', () => {
  it('seeds work_order_id and resolves its "code — title" label from the cached detail', async () => {
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    client.setQueryData(workOrderDetailQueryKey(4), { id: 4, code: 'COM-0004', title: 'Installazione impianto' })
    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'create', workOrderId: 4 }, onSuccess: () => undefined }),
      {
        wrapper: ({ children }: { children: ReactNode }) => (
          <QueryClientProvider client={client}>{children}</QueryClientProvider>
        ),
      },
    )

    expect(result.current.form.getValues('work_order_id')).toBe(4)
    await waitFor(() =>
      expect(result.current.workOrderPrefillRef).toEqual({ id: 4, name: 'COM-0004 — Installazione impianto' }),
    )
    expect(fetchWorkOrderMock).not.toHaveBeenCalled()
  })

  it('leaves the work order empty and unresolved when the create form has no context', () => {
    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'create' }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    expect(result.current.form.getValues('work_order_id')).toBeNull()
    expect(result.current.workOrderPrefillRef).toBeNull()
  })
})

describe('useTaskForm — sub-task prefill (AC-085)', () => {
  it('seeds parent_task_id from the create mode', () => {
    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'create', parentTaskId: 90 }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    expect(result.current.form.getValues('parent_task_id')).toBe(90)
  })
})

/** The fixture used by every parent-prefill test below: every link populated, a 10-day range. */
function parentFixture(overrides: Partial<TaskDetailWithPermissions> = {}): TaskDetailWithPermissions {
  return taskDetailWithPermissions({
    id: 90,
    registry_id: 7,
    registry: { id: 7, name: 'Acme' },
    referent_id: 11,
    referent: { id: 11, name: 'Ada Alberti' },
    opportunity_id: 8,
    opportunity: { id: 8, name: 'Opportunita X' },
    work_order_id: 9,
    work_order: { id: 9, code: 'WO-1', title: 'Commessa Uno' },
    start_date: '2026-09-10',
    end_date: '2026-09-20',
    ...overrides,
  })
}

/**
 * Spec 0123 D-10 (link prefill, client-only) + D-7 (date-range mirror),
 * create-only: AC-037/AC-038/AC-029.
 */
describe('useTaskForm — parent prefill on create (spec 0123 D-10/D-7)', () => {
  it('prefills the four empty link fields from the parent (AC-037)', async () => {
    fetchTaskMock.mockResolvedValue(parentFixture())
    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'create', parentTaskId: 90 }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(result.current.form.getValues('registry_id')).toBe(7))

    expect(result.current.form.getValues('referent_id')).toBe(11)
    expect(result.current.form.getValues('opportunity_id')).toBe(8)
    expect(result.current.form.getValues('work_order_id')).toBe(9)
    expect(result.current.parentPrefillRefs.registry).toEqual({ id: 7, name: 'Acme' })
    expect(result.current.parentPrefillRefs.workOrder).toEqual({ id: 9, name: 'WO-1 — Commessa Uno' })
  })

  it('never overwrites a link already filled by the user (AC-038)', async () => {
    fetchTaskMock.mockResolvedValue(parentFixture())
    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'create', parentTaskId: 90 }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    act(() => {
      result.current.form.setValue('registry_id', 42)
    })

    await waitFor(() => expect(result.current.form.getValues('referent_id')).toBe(11))

    // The user's own pick survives; the fields the user left empty still fill in.
    expect(result.current.form.getValues('registry_id')).toBe(42)
    expect(result.current.form.getValues('opportunity_id')).toBe(8)
  })

  // The parent detail behind the "crea sotto-task" Sheet shares this query
  // key: a refetch here would blank it into its skeleton while the Sheet opens.
  it('reuses a cached parent without refetching it', async () => {
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    client.setQueryData(taskDetailQueryKey(90), parentFixture())
    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'create', parentTaskId: 90 }, onSuccess: () => undefined }),
      { wrapper: ({ children }: { children: ReactNode }) => (
        <QueryClientProvider client={client}>{children}</QueryClientProvider>
      ) },
    )

    await waitFor(() => expect(result.current.form.getValues('registry_id')).toBe(7))
    expect(fetchTaskMock).not.toHaveBeenCalled()
  })

  it('never fetches the parent in edit mode', async () => {
    const task = taskDetailWithPermissions({ parent_task_id: 90 })
    renderHook(() => useTaskForm({ mode: { type: 'edit', task }, onSuccess: () => undefined }), {
      wrapper: wrapper(),
    })

    await Promise.resolve()
    expect(fetchTaskMock).not.toHaveBeenCalled()
  })

  it('blocks a date outside the parent range with an inline error (AC-029)', async () => {
    fetchTaskMock.mockResolvedValue(parentFixture())
    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'create', parentTaskId: 90 }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(result.current.form.getValues('registry_id')).toBe(7))

    act(() => {
      result.current.form.setValue('end_date', '2026-09-25')
    })
    await act(async () => {
      await result.current.form.trigger('end_date')
    })

    expect(result.current.form.getFieldState('end_date').error?.message).toBe(
      i18n.t('tasks.form.endDateOutsideParentRange'),
    )
  })

  it('accepts a date inside the parent range', async () => {
    fetchTaskMock.mockResolvedValue(parentFixture())
    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'create', parentTaskId: 90 }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(result.current.form.getValues('registry_id')).toBe(7))

    act(() => {
      result.current.form.setValue('end_date', '2026-09-15')
    })
    await act(async () => {
      await result.current.form.trigger('end_date')
    })

    expect(result.current.form.getFieldState('end_date').error).toBeUndefined()
  })
})

/**
 * Spec 0156 D-4: row action "duplicate" pre-fills the create form from the
 * source task, except the parent link (never copied), the status (re-derived
 * server-side) and the sub-tasks block (create-only, always starts empty).
 */
describe('useTaskForm — duplicate defaults (spec 0156 D-4)', () => {
  it('copies every field but drops the parent, the status and the sub-tasks', () => {
    const source = taskDetailWithPermissions({
      title: 'Richiamare il cliente',
      parent_task_id: 55,
      parent_task: { id: 55, title: 'Task padre' },
      task_status_id: 3,
    })
    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'duplicate', source }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    const values = result.current.form.getValues()
    expect(values.title).toBe(`Richiamare il cliente${i18n.t('common.copySuffix')}`)
    expect(values.parent_task_id).toBeNull()
    expect(values.task_status_id).toBeNull()
    expect(values.subtasks).toEqual([])
    expect(values.is_completed).toBe(false)
    expect(values.suppress_notifications).toBe(false)
    // Everything else copies over.
    expect(values.registry_id).toBe(source.registry_id)
    expect(values.work_order_id).toBe(source.work_order_id)
    expect(values.requester_id).toBe(source.requester_id)
    expect(values.assignee_ids).toEqual(source.assignees.map((user) => user.id))
    expect(values.watcher_ids).toEqual(source.watchers.map((user) => user.id))
    expect(values.end_date).toBe(source.end_date)
  })

  it('submits through the create endpoint like a bare create (isEdit false)', () => {
    const source = taskDetailWithPermissions()
    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'duplicate', source }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    expect(result.current.isEdit).toBe(false)
  })
})

/** Keeps the fixture honest: the status projection is what seeds the readout. */
describe('taskStatus fixture', () => {
  it('carries the columns only task_statuses has (D-4), phase included', () => {
    expect(taskStatus()).toMatchObject({
      system_key: 'open',
      group: 'open',
      completion_percentage: 25,
    })
  })
})
