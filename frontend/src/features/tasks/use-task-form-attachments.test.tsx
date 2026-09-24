import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { act, renderHook } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { createTask, TASK_ATTACHABLE_ALIAS } from '@/features/tasks/api'
import { uploadAttachment } from '@/features/attachments/api'
import { DOCUMENTS_COLLECTION } from '@/features/attachments/types'
import { useTaskForm } from '@/features/tasks/use-task-form'
import { taskDetail } from '@/features/tasks/task-fixtures'
import type { TaskFormValues } from '@/features/tasks/task-schema'
import type { UseFormReturn } from 'react-hook-form'

/**
 * Split off `use-task-form.test.tsx` (spec 0118 D-7/D-8, AC-023..AC-026):
 * the attachment-staging orchestration is a whole concern of its own and the
 * host file was already at the file-size limit — same reasoning that split
 * `use-task-form-server-errors.test.tsx` off earlier.
 */

vi.mock('@/features/tasks/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/tasks/api')>('@/features/tasks/api')
  return { ...actual, createTask: vi.fn(), updateTask: vi.fn() }
})

/** D-7/D-8 orchestration reads `uploadAttachment` directly, never `useAttachments`: that hook is scoped to an EXISTING owner and would need an id this form does not have yet. */
vi.mock('@/features/attachments/api', () => ({ uploadAttachment: vi.fn() }))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

/** These tests only exercise the D-7/D-8 orchestration, not the D-1 prefill: a fixed actor is enough. */
vi.mock('@/features/auth/use-auth', () => ({
  useAuth: () => ({ user: { id: 99, name: 'Utente Corrente' } }),
}))

/** A stable `QueryClient` per test, never per render: a per-render one resets the cache and flakes. */
function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

/**
 * Fills in the fields the create schema actually requires (title/assignees/
 * end_date, D-1, plus type/priority/importance, spec 0154 D-8); `requester_id`
 * is already prefilled. The D-8 precompile effect is not exercised here — it
 * has its own coverage in `use-task-form.test.tsx` — so these three are set
 * directly rather than relying on a mocked for-select default row.
 */
function fillMinimalCreateValues(form: UseFormReturn<TaskFormValues>) {
  form.setValue('title', 'Richiamare il cliente')
  form.setValue('assignee_ids', [31])
  form.setValue('end_date', '2026-09-05')
  form.setValue('task_type_id', 2)
  form.setValue('task_priority_id', 4)
  form.setValue('task_importance_id', 5)
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.mocked(uploadAttachment).mockReset()
  vi.mocked(toast.success).mockClear()
  vi.mocked(toast.error).mockClear()
})

describe('useTaskForm — attachment staging upload after create (spec 0118 D-7/AC-023)', () => {
  it('POSTs the task once, then uploads every staged file against the returned id', async () => {
    const created = taskDetail({ id: 123 })
    vi.mocked(createTask).mockResolvedValueOnce(created)
    vi.mocked(uploadAttachment).mockResolvedValue({} as never)

    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'create' }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    const fileA = new File(['a'], 'contratto.pdf', { type: 'application/pdf' })
    const fileB = new File(['b'], 'foto.png', { type: 'image/png' })

    act(() => {
      fillMinimalCreateValues(result.current.form)
      result.current.addStagedAttachments([fileA, fileB])
    })

    await act(async () => {
      await result.current.form.handleSubmit(result.current.onSubmit)()
    })

    expect(createTask).toHaveBeenCalledTimes(1)
    expect(uploadAttachment).toHaveBeenCalledTimes(2)
    expect(uploadAttachment).toHaveBeenNthCalledWith(1, {
      resource: TASK_ATTACHABLE_ALIAS,
      id: created.id,
      collection: DOCUMENTS_COLLECTION,
      file: fileA,
    })
    expect(uploadAttachment).toHaveBeenNthCalledWith(2, {
      resource: TASK_ATTACHABLE_ALIAS,
      id: created.id,
      collection: DOCUMENTS_COLLECTION,
      file: fileB,
    })
  })
})

/**
 * D-8: the task is already saved by the time an attachment can fail — a
 * rejected upload never rolls it back and never re-traps the user on the
 * form; the caller still navigates (`onSuccess`) and the toast names the
 * culprit.
 */
describe('useTaskForm — partial upload failure keeps the task, names the miss (spec 0118 D-8/AC-024)', () => {
  it('still calls onSuccess and toasts the rejected file by name', async () => {
    const created = taskDetail({ id: 124 })
    vi.mocked(createTask).mockResolvedValueOnce(created)
    const fileOk = new File(['a'], 'contratto.pdf', { type: 'application/pdf' })
    const fileBad = new File(['b'], 'foto.png', { type: 'image/png' })
    vi.mocked(uploadAttachment).mockImplementation(async ({ file }) => {
      if (file === fileBad) {
        throw new Error('server rejected the file')
      }
      return {} as never
    })

    const onSuccess = vi.fn()
    const { result } = renderHook(() => useTaskForm({ mode: { type: 'create' }, onSuccess }), {
      wrapper: wrapper(),
    })

    act(() => {
      fillMinimalCreateValues(result.current.form)
      result.current.addStagedAttachments([fileOk, fileBad])
    })

    await act(async () => {
      await result.current.form.handleSubmit(result.current.onSubmit)()
    })

    expect(onSuccess).toHaveBeenCalledWith(created)
    expect(toast.error).toHaveBeenCalledWith(
      i18n.t('tasks.form.attachments.uploadFailed', { files: 'foto.png' }),
    )
  })
})

/** D-7: an empty staging area never dials the network at all (AC-025). */
describe('useTaskForm — no staged file, no attachment call (spec 0118 D-7/AC-025)', () => {
  it('never calls uploadAttachment when nothing was staged', async () => {
    vi.mocked(createTask).mockResolvedValueOnce(taskDetail({ id: 125 }))

    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'create' }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    act(() => {
      fillMinimalCreateValues(result.current.form)
    })

    await act(async () => {
      await result.current.form.handleSubmit(result.current.onSubmit)()
    })

    expect(uploadAttachment).not.toHaveBeenCalled()
  })
})

describe('useTaskForm — staged attachments state (AC-026)', () => {
  it('adds picked files and removes exactly the one at the given index', () => {
    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'create' }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )
    const fileA = new File(['a'], 'a.pdf')
    const fileB = new File(['b'], 'b.pdf')

    act(() => {
      result.current.addStagedAttachments([fileA, fileB])
    })
    expect(result.current.stagedAttachments).toEqual([fileA, fileB])

    act(() => {
      result.current.removeStagedAttachment(0)
    })
    expect(result.current.stagedAttachments).toEqual([fileB])
  })

  it('never uploads a file removed from staging before the save', async () => {
    vi.mocked(createTask).mockResolvedValueOnce(taskDetail({ id: 126 }))
    const fileA = new File(['a'], 'a.pdf')
    const fileB = new File(['b'], 'b.pdf')

    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'create' }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    act(() => {
      fillMinimalCreateValues(result.current.form)
      result.current.addStagedAttachments([fileA, fileB])
      result.current.removeStagedAttachment(0)
    })

    await act(async () => {
      await result.current.form.handleSubmit(result.current.onSubmit)()
    })

    expect(uploadAttachment).toHaveBeenCalledTimes(1)
    expect(uploadAttachment).toHaveBeenCalledWith(expect.objectContaining({ file: fileB }))
  })
})
