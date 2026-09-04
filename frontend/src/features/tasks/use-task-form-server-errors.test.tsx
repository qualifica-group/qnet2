import { beforeAll, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { act, renderHook } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError, AxiosHeaders } from 'axios'
import i18n from '@/i18n'
import { createTask } from '@/features/tasks/api'
import { useTaskForm } from '@/features/tasks/use-task-form'

vi.mock('@/features/tasks/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/tasks/api')>('@/features/tasks/api')
  return { ...actual, createTask: vi.fn(), updateTask: vi.fn() }
})

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
    })
    await act(async () => {
      await result.current.form.handleSubmit(result.current.onSubmit)()
    })

    expect(result.current.serverError).not.toBeNull()
  })
})
