import { beforeAll, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { act, renderHook } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { useTaskForm } from '@/features/tasks/use-task-form'
import { taskDetailWithPermissions, taskStatus } from '@/features/tasks/task-fixtures'
import { DEFAULT_MODULE_OPEN_PREFERENCES } from '@/features/modules/types'
import type { TaskStatusForSelectItem } from '@/features/tasks/for-select-api'
import type { User } from '@/features/auth/types'

vi.mock('@/features/tasks/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/tasks/api')>('@/features/tasks/api')
  return { ...actual, createTask: vi.fn(), updateTask: vi.fn() }
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
      result.current.handleRegistryChange()
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
      result.current.handleRegistryChange()
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

describe('useTaskForm — sub-task prefill (AC-085)', () => {
  it('seeds parent_task_id from the create mode', () => {
    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'create', parentTaskId: 90 }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    expect(result.current.form.getValues('parent_task_id')).toBe(90)
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
