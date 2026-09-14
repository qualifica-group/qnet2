import { beforeAll, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { act, renderHook } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError, AxiosHeaders } from 'axios'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { createTask, updateTask } from '@/features/tasks/api'
import { useTaskForm } from '@/features/tasks/use-task-form'
import { taskDetailWithPermissions } from '@/features/tasks/task-fixtures'

vi.mock('@/features/tasks/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/tasks/api')>('@/features/tasks/api')
  return { ...actual, createTask: vi.fn(), updateTask: vi.fn() }
})

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

/** These tests only exercise the 422/network mapping, not the D-1 prefill: a fixed actor is enough. */
vi.mock('@/features/auth/use-auth', () => ({
  useAuth: () => ({ user: { id: 99, name: 'Utente Corrente' } }),
}))

/** A stable `QueryClient` per test, never per render. */
function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

/** A 422 carrying a field-scoped `errors` block, the shape `form-errors.ts` maps. */
function validationError(errors: Record<string, string[]>): AxiosError {
  const error = new AxiosError('Unprocessable Content')
  error.response = {
    status: 422,
    statusText: 'Unprocessable Content',
    data: { success: false, message: 'The given data was invalid.', errors },
    headers: {},
    config: { headers: new AxiosHeaders() },
  }
  return error
}

/**
 * The server stays the authority on every rule the client mirrors: the 422
 * path is never removed. `TaskHierarchyGuard` (D-12) and
 * `TaskClosureFeedbackGuard` (D-7) both answer FIELD-SCOPED, so their refusals
 * must land on the field, not on the form-level banner.
 */
describe('useTaskForm — server refusals land on their field (D-7/D-12)', () => {
  it('maps a self-parent/cycle 422 onto the "Task padre" field', async () => {
    vi.mocked(createTask).mockRejectedValueOnce(
      validationError({ parent_task_id: ['A task cannot be its own parent.'] }),
    )
    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'create' }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    act(() => {
      result.current.form.setValue('title', 'Sotto-attivita')
      result.current.form.setValue('task_status_id', 3)
      result.current.form.setValue('assignee_ids', [31])
      result.current.form.setValue('end_date', '2026-09-05')
    })
    await act(async () => {
      await result.current.form.handleSubmit(result.current.onSubmit)()
    })

    expect(result.current.form.getFieldState('parent_task_id').error?.message).toBe(
      'A task cannot be its own parent.',
    )
    expect(result.current.serverError).toBeNull()
  })

  it('maps a referent/registry mismatch 422 onto the referent field (AC-014)', async () => {
    vi.mocked(createTask).mockRejectedValueOnce(
      validationError({ referent_id: ['The selected referent does not belong to that registry.'] }),
    )
    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'create' }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    act(() => {
      result.current.form.setValue('title', 'Richiamare')
      result.current.form.setValue('task_status_id', 3)
      result.current.form.setValue('assignee_ids', [31])
      result.current.form.setValue('end_date', '2026-09-05')
    })
    await act(async () => {
      await result.current.form.handleSubmit(result.current.onSubmit)()
    })

    expect(result.current.form.getFieldState('referent_id').error).toBeDefined()
  })

  it('falls back to the form-level banner for a non-422 failure', async () => {
    vi.mocked(createTask).mockRejectedValueOnce(new Error('network down'))
    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'create' }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    act(() => {
      result.current.form.setValue('title', 'Richiamare')
      result.current.form.setValue('task_status_id', 3)
      result.current.form.setValue('assignee_ids', [31])
      result.current.form.setValue('end_date', '2026-09-05')
    })
    await act(async () => {
      await result.current.form.handleSubmit(result.current.onSubmit)()
    })

    expect(result.current.serverError).not.toBeNull()
  })
})

/**
 * Spec 0121 AC-022: `closure_feedback` left the form entirely (D-7) and
 * `task_status_id`'s refusal here is a workflow guard (D-5), not a
 * picker-level validation — both have nowhere to land, so they surface as a
 * toast with the server's own message instead of a field error.
 */
describe('useTaskForm — AC-022: closure_feedback/task_status_id 422 on the PATCH surface as a toast', () => {
  it('toasts a closure_feedback refusal instead of setting a field error', async () => {
    const task = taskDetailWithPermissions()
    vi.mocked(updateTask).mockRejectedValueOnce(
      validationError({ closure_feedback: ['A closure feedback is required to close this task.'] }),
    )
    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'edit', task }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    act(() => {
      result.current.form.setValue('title', 'Chiudere la pratica')
    })
    await act(async () => {
      await result.current.form.handleSubmit(result.current.onSubmit)()
    })

    expect(toast.error).toHaveBeenCalledWith('A closure feedback is required to close this task.')
    expect(result.current.serverError).toBeNull()
  })

  it('toasts a task_status_id refusal instead of setting a field error', async () => {
    const task = taskDetailWithPermissions()
    vi.mocked(updateTask).mockRejectedValueOnce(
      validationError({ task_status_id: ['This task requires validation before it can close.'] }),
    )
    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'edit', task }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    act(() => {
      result.current.form.setValue('title', 'Chiudere la pratica')
    })
    await act(async () => {
      await result.current.form.handleSubmit(result.current.onSubmit)()
    })

    expect(toast.error).toHaveBeenCalledWith('This task requires validation before it can close.')
    expect(result.current.form.getFieldState('task_status_id').error).toBeUndefined()
  })

  /** Spec 0120: the bare `recurrence` key (D-13 frozen task, D-1 missing end_date) has no field to land on either. */
  it('toasts a bare recurrence refusal instead of setting a field error', async () => {
    const task = taskDetailWithPermissions()
    vi.mocked(updateTask).mockRejectedValueOnce(
      validationError({ recurrence: ['This task is frozen and cannot carry a recurrence rule.'] }),
    )
    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'edit', task }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    act(() => {
      result.current.form.setValue('title', 'Chiudere la pratica')
    })
    await act(async () => {
      await result.current.form.handleSubmit(result.current.onSubmit)()
    })

    expect(toast.error).toHaveBeenCalledWith('This task is frozen and cannot carry a recurrence rule.')
  })
})

/** Spec 0120 AC-026: a field-scoped `recurrence.*` 422 lands on the matching picker/input. */
describe('useTaskForm — recurrence.* 422 lands on its own field', () => {
  it('maps recurrence.weekdays onto the weekdays field', async () => {
    const task = taskDetailWithPermissions()
    vi.mocked(updateTask).mockRejectedValueOnce(
      validationError({ 'recurrence.weekdays': ['The recurrence.weekdays field is required.'] }),
    )
    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'edit', task }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    act(() => {
      result.current.form.setValue('title', 'Chiudere la pratica')
    })
    await act(async () => {
      await result.current.form.handleSubmit(result.current.onSubmit)()
    })

    expect(result.current.form.getFieldState('recurrence.weekdays').error?.message).toBe(
      'The recurrence.weekdays field is required.',
    )
  })
})
