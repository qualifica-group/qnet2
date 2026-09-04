import { beforeAll, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { act, renderHook } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { createTask } from '@/features/tasks/api'
import { useTaskForm } from '@/features/tasks/use-task-form'
import { taskDetailWithPermissions, taskStatus } from '@/features/tasks/task-fixtures'
import type { TaskStatusForSelectItem } from '@/features/tasks/for-select-api'

vi.mock('@/features/tasks/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/tasks/api')>('@/features/tasks/api')
  return { ...actual, createTask: vi.fn(), updateTask: vi.fn() }
})

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
    expect(result.current.statusGroup).toBe('open')
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
    expect(result.current.statusGroup).toBe('closed_positive')
    expect(result.current.form.getValues()).not.toHaveProperty('completion_percentage')
  })

  /**
   * An ORDINARY status (`system_key: null`) still arrives fully projected, and
   * since the 2026-09-04 rectification its `group` is what the rule reads. So
   * the absent system key disarms NOTHING on its own: the phase does. Both
   * directions are asserted here, because the old suite only ever covered the
   * one that made the missing key look decisive.
   */
  it('reads the phase of an ORDINARY status, not its absent system key', () => {
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
    expect(result.current.statusGroup).toBe('pending')

    act(() => {
      result.current.handleStatusItemChange(
        statusOption({ system_key: null, group: 'closed_positive', completion_percentage: 100 }),
      )
    })

    expect(result.current.statusGroup).toBe('closed_positive')
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

/** D-7 replicated for UX: the rule re-arms as soon as a closing status is picked. */
describe('useTaskForm — closure feedback rule follows the picked status (D-7)', () => {
  it('blocks the submit when the flag is on and a closing status is picked without feedback', async () => {
    const onSuccess = vi.fn()
    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'create' }, onSuccess }),
      { wrapper: wrapper() },
    )

    act(() => {
      result.current.form.setValue('title', 'Chiudere la pratica')
      result.current.form.setValue('task_status_id', 6)
      result.current.form.setValue('requires_closure_feedback', true)
      result.current.handleStatusItemChange(statusOption())
    })

    await act(async () => {
      await result.current.form.handleSubmit(result.current.onSubmit)()
    })

    expect(result.current.form.getFieldState('closure_feedback').error).toBeDefined()
    expect(createTask).not.toHaveBeenCalled()
    expect(onSuccess).not.toHaveBeenCalled()
  })

  /**
   * The 2026-09-04 rectification, end to end. An ORDINARY status carries no
   * `system_key`, so the old rule read `null`, armed nothing and let the submit
   * reach the server — where the user met a bare 422. Only the PHASE can tell
   * this status closes the task, so this case fails on any version of the rule
   * that still branches on the key.
   */
  it('blocks the submit on an ORDINARY status sitting in a closing phase', async () => {
    const onSuccess = vi.fn()
    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'create' }, onSuccess }),
      { wrapper: wrapper() },
    )

    act(() => {
      result.current.form.setValue('title', 'Chiudere la pratica')
      result.current.form.setValue('task_status_id', 7)
      result.current.form.setValue('requires_closure_feedback', true)
      result.current.handleStatusItemChange(
        statusOption({ system_key: null, group: 'closed_negative' }),
      )
    })

    await act(async () => {
      await result.current.form.handleSubmit(result.current.onSubmit)()
    })

    expect(result.current.form.getFieldState('closure_feedback').error).toBeDefined()
    expect(createTask).not.toHaveBeenCalled()
    expect(onSuccess).not.toHaveBeenCalled()
  })

  it('lets the submit through on a status that closes nothing (AC-034)', async () => {
    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'create' }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    act(() => {
      result.current.form.setValue('title', 'Attivita in corso')
      result.current.form.setValue('task_status_id', 3)
      result.current.form.setValue('requires_closure_feedback', true)
      result.current.handleStatusItemChange(
        statusOption({ system_key: 'open', group: 'open', completion_percentage: 25 }),
      )
    })

    await act(async () => {
      await result.current.form.trigger()
    })

    expect(result.current.form.getFieldState('closure_feedback').error).toBeUndefined()
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
