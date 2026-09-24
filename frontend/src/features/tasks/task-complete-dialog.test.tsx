import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError, AxiosHeaders } from 'axios'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { TaskCompleteDialog } from '@/features/tasks/task-complete-dialog'
import { completeTask } from '@/features/tasks/api'
import { FULL_ACCESS_PERMISSIONS, taskDetailWithPermissions, taskStatus } from '@/features/tasks/task-fixtures'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { TaskDetailWithPermissions } from '@/features/tasks/types'

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/tasks/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/tasks/api')>('@/features/tasks/api')
  return { ...actual, completeTask: vi.fn() }
})

// The validation-status picker mounts only inside the "richiedi validazione"
// branch; stubbed to a plain trigger so these tests exercise the dialog, not
// the async for-select network path (already covered by `async-paginated-select.test.tsx`).
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    labels,
    onChange,
  }: {
    labels: { triggerLabel: string }
    onChange: (value: number | null) => void
  }) => (
    <button type="button" onClick={() => onChange(50)}>
      {labels.triggerLabel}
    </button>
  ),
}))

// The "Segnatempo" section's type field mounts a second async for-select
// network path (spec 0123 D-1); stubbed with a single, already-active type
// matching the fixture's own `task_type_id` (2) so the section's default
// resolves without any interaction.
vi.mock('@/features/time-entries/form/time-entry-type-picker', () => ({
  useTimeEntryTypeOptions: () => ({
    options: [{ id: 2, label: 'Attività', meta: { color: 'blue', icon: 'clipboard-list' } }],
    isPending: false,
    isError: false,
    refetch: vi.fn(),
  }),
  TimeEntryTypePicker: ({ value }: { value: number | null }) => <div>{value ?? ''}</div>,
}))

const label = (key: string) => i18n.t(key)

/** `FULL_ACCESS_PERMISSIONS` with only the given action flags set — the rest default to false/absent. */
function actionPermissions(actions: ResourcePermissions['actions']): ResourcePermissions {
  return { ...FULL_ACCESS_PERMISSIONS, actions }
}

/** A 422 carrying field-scoped `errors` (spec 0123 AC-040: `time_entry.*`). */
function fieldValidationError(errors: Record<string, string[]>): AxiosError {
  const error = new AxiosError('failed')
  error.response = {
    status: 422,
    statusText: 'Unprocessable Content',
    data: { success: false, message: 'failed', errors },
    headers: {},
    config: { headers: new AxiosHeaders() },
  }
  return error
}

function openCompleteDialog(
  overrides: Partial<TaskDetailWithPermissions> = {},
  forAllAssignees = true,
) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <TaskCompleteDialog
        open
        onOpenChange={vi.fn()}
        task={taskDetailWithPermissions({
          permissions: actionPermissions({ complete: true, complete_to_validation: false }),
          ...overrides,
        })}
        forAllAssignees={forAllAssignees}
      />
    </QueryClientProvider>,
  )
  return screen.findByRole('button', { name: label('tasks.actions.completeDialog.confirm') })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.mocked(completeTask).mockReset()
  vi.mocked(toast.success).mockReset()
  vi.mocked(toast.error).mockReset()
})

/**
 * Spec 0121 D-2/D-3/D-6 RECTIFIES spec 0116 D-8: the path is derived
 * server-side (`permissions.actions.complete_to_validation`), never picked by
 * a switch in this dialog. Spec 0123 D-1 makes the "Segnatempo" section
 * mandatory on both paths (AC-039/AC-040).
 */
describe('TaskCompleteDialog', () => {
  it('keeps the submit disabled while a required feedback is empty (AC-020)', async () => {
    const submit = await openCompleteDialog({ requires_closure_feedback: true })

    expect(submit).toBeDisabled()

    // A required `FormLabel` appends an `aria-hidden` "*", part of the label's
    // plain text content: anchor the match instead of an exact string.
    fireEvent.change(screen.getByLabelText(new RegExp(`^${label('tasks.actions.completeDialog.feedback')}`)), {
      target: { value: 'Consegnato al cliente' },
    })

    expect(submit).toBeEnabled()
  })

  it('leaves the submit enabled with no feedback when none is required', async () => {
    const submit = await openCompleteDialog({ requires_closure_feedback: false })

    expect(submit).toBeEnabled()
  })

  /**
   * Spec 0123 D-1: `time_entry` is now mandatory on `/complete` — filling
   * "Time" (the segnatempo section's minutes field, reused from
   * `TimeEntryEditor`) is a new precondition to reach the API on both paths.
   */
  it('with complete_to_validation false: no switch, no status picker, and the segnatempo payload (AC-018)', async () => {
    vi.mocked(completeTask).mockResolvedValueOnce(taskDetailWithPermissions())
    await openCompleteDialog({ requires_closure_feedback: false })

    expect(screen.queryByRole('switch')).not.toBeInTheDocument()
    expect(
      screen.queryByRole('button', { name: label('tasks.actions.completeDialog.validationStatus') }),
    ).not.toBeInTheDocument()

    // The minutes field is required, so its `FormLabel` appends an
    // `aria-hidden` "*": anchor the match instead of an exact string.
    fireEvent.change(screen.getByLabelText(new RegExp(`^${label('timeEntries.form.minutes')}`)), {
      target: { value: '01:00' },
    })
    fireEvent.click(screen.getByRole('button', { name: label('tasks.actions.completeDialog.confirm') }))

    await waitFor(() =>
      expect(completeTask).toHaveBeenCalledWith(90, {
        time_entry: {
          date: expect.any(String),
          task_type_id: 2,
          minutes: 60,
          start_time: null,
          end_time: null,
          notes: null,
        },
        for_all_assignees: true,
      }),
    )
  })

  /** Spec 0155 D-6: the sub-task panel opens this same dialog with `forAllAssignees={false}` — the key is omitted, not sent as `false`. */
  it('with forAllAssignees false (sub-task panel): omits for_all_assignees from the payload', async () => {
    vi.mocked(completeTask).mockResolvedValueOnce(taskDetailWithPermissions())
    await openCompleteDialog({ requires_closure_feedback: false }, false)

    fireEvent.change(screen.getByLabelText(new RegExp(`^${label('timeEntries.form.minutes')}`)), {
      target: { value: '01:00' },
    })
    fireEvent.click(screen.getByRole('button', { name: label('tasks.actions.completeDialog.confirm') }))

    await waitFor(() => expect(completeTask).toHaveBeenCalled())
    const [, payload] = vi.mocked(completeTask).mock.calls[0]
    expect(payload).not.toHaveProperty('for_all_assignees')
  })

  it('with complete_to_validation true: "Invia in validazione" title, mandatory status picker, no switch (AC-019)', async () => {
    vi.mocked(completeTask).mockResolvedValueOnce(
      taskDetailWithPermissions({ task_status: taskStatus({ id: 50, group: 'in_validation' }) }),
    )
    await openCompleteDialog({
      requires_closure_feedback: false,
      permissions: actionPermissions({ complete: true, complete_to_validation: true }),
    })

    expect(
      screen.getByRole('heading', { name: label('tasks.actions.completeDialog.validationTitle') }),
    ).toBeInTheDocument()
    expect(screen.queryByRole('switch')).not.toBeInTheDocument()

    // Submitting without a chosen status shows the field error and never calls the API.
    fireEvent.click(screen.getByRole('button', { name: label('tasks.actions.completeDialog.confirm') }))
    expect(
      await screen.findByText(label('tasks.actions.completeDialog.validationStatusRequired')),
    ).toBeInTheDocument()
    expect(completeTask).not.toHaveBeenCalled()

    fireEvent.click(screen.getByRole('button', { name: label('tasks.actions.completeDialog.validationStatus') }))
    fireEvent.change(screen.getByLabelText(new RegExp(`^${label('timeEntries.form.minutes')}`)), {
      target: { value: '01:00' },
    })
    fireEvent.click(screen.getByRole('button', { name: label('tasks.actions.completeDialog.confirm') }))

    await waitFor(() =>
      expect(completeTask).toHaveBeenCalledWith(90, {
        validation_status_id: 50,
        time_entry: {
          date: expect.any(String),
          task_type_id: 2,
          minutes: 60,
          start_time: null,
          end_time: null,
          notes: null,
        },
        for_all_assignees: true,
      }),
    )
  })

  it('blocks the submit and shows the inline error when the segnatempo minutes are empty (AC-039)', async () => {
    await openCompleteDialog({ requires_closure_feedback: false })

    fireEvent.click(screen.getByRole('button', { name: label('tasks.actions.completeDialog.confirm') }))

    expect(await screen.findByText(label('timeEntries.form.minutesRequired'))).toBeInTheDocument()
    expect(completeTask).not.toHaveBeenCalled()
  })

  it('wires a 422 on time_entry.minutes inline on the segnatempo field (AC-040)', async () => {
    vi.mocked(completeTask).mockRejectedValueOnce(
      fieldValidationError({ 'time_entry.minutes': ['I minuti devono essere tra 1 e 1440.'] }),
    )
    await openCompleteDialog({ requires_closure_feedback: false })

    fireEvent.change(screen.getByLabelText(new RegExp(`^${label('timeEntries.form.minutes')}`)), {
      target: { value: '01:00' },
    })
    fireEvent.click(screen.getByRole('button', { name: label('tasks.actions.completeDialog.confirm') }))

    expect(await screen.findByText('I minuti devono essere tra 1 e 1440.')).toBeInTheDocument()
  })
})
