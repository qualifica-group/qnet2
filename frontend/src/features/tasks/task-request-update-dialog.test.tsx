import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError, AxiosHeaders } from 'axios'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { TaskRequestUpdateDialog } from '@/features/tasks/task-request-update-dialog'
import { requestTaskUpdate } from '@/features/tasks/api'
import { taskDetailWithPermissions } from '@/features/tasks/task-fixtures'
import type { TaskDetailWithPermissions } from '@/features/tasks/types'

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/tasks/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/tasks/api')>('@/features/tasks/api')
  return { ...actual, requestTaskUpdate: vi.fn() }
})

const label = (key: string) => i18n.t(key)

/** A 409/422 shaped so `axios.isAxiosError` recognizes it (mirrors `task-detail.test.tsx`). */
function actionError(status: 409 | 422, errors?: Record<string, string[]>): AxiosError {
  const error = new AxiosError('failed')
  error.response = {
    status,
    statusText: status === 409 ? 'Conflict' : 'Unprocessable Content',
    data: { success: false, message: 'failed', errors },
    headers: {},
    config: { headers: new AxiosHeaders() },
  }
  return error
}

function renderDialog(overrides: Partial<TaskDetailWithPermissions> = {}) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const task = taskDetailWithPermissions(overrides)
  const onOpenChange = vi.fn()
  render(
    <QueryClientProvider client={client}>
      <TaskRequestUpdateDialog open onOpenChange={onOpenChange} task={task} />
    </QueryClientProvider>,
  )
  return { task, onOpenChange }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.mocked(requestTaskUpdate).mockReset()
  vi.mocked(toast.success).mockReset()
  vi.mocked(toast.error).mockReset()
})

describe('TaskRequestUpdateDialog — recipient picker (AC-062)', () => {
  it('offers only this task\'s assignees and watchers', () => {
    renderDialog()

    expect(screen.getByRole('checkbox', { name: /Dario Dini/ })).toBeInTheDocument()
    expect(screen.getByRole('checkbox', { name: /Elsa Esposito/ })).toBeInTheDocument()
    expect(screen.getByRole('checkbox', { name: /Fabio Fini/ })).toBeInTheDocument()
  })

  it('never offers the creator or the requester on their own', () => {
    renderDialog()

    expect(screen.queryByRole('checkbox', { name: /Carla Conti/ })).not.toBeInTheDocument()
    expect(screen.queryByRole('checkbox', { name: /Bruno Bianchi/ })).not.toBeInTheDocument()
  })

  it('shows the empty state and disables submit when the task has no assignees nor watchers', () => {
    renderDialog({ assignees: [], watchers: [] })

    expect(screen.getByText(label('tasks.actions.requestUpdate.recipientsEmpty'))).toBeInTheDocument()
    expect(screen.getByRole('button', { name: label('tasks.actions.requestUpdate.submit') })).toBeDisabled()
  })
})

describe('TaskRequestUpdateDialog — dedupe and default selection (AC-014, spec 0126 D-7)', () => {
  it('opens with every candidate selected by default', () => {
    renderDialog()

    expect(screen.getByRole('checkbox', { name: /Dario Dini/ })).toBeChecked()
    expect(screen.getByRole('checkbox', { name: /Elsa Esposito/ })).toBeChecked()
    expect(screen.getByRole('checkbox', { name: /Fabio Fini/ })).toBeChecked()
  })

  it('shows a user who is both an assignee and a watcher only once, with both role labels', () => {
    renderDialog({ watchers: [{ id: 41, name: 'Fabio Fini' }, { id: 31, name: 'Dario Dini' }] })

    expect(screen.getAllByRole('checkbox', { name: /Dario Dini/ })).toHaveLength(1)
    const dario = screen.getByRole('checkbox', { name: /Dario Dini/ })
    expect(dario.getAttribute('aria-label') ?? dario.closest('label')?.textContent).toMatch(/Assignee/)
    expect(dario.getAttribute('aria-label') ?? dario.closest('label')?.textContent).toMatch(/Watcher/)
  })

  it('sends unique recipient ids even when a candidate was merged from both lists', async () => {
    vi.mocked(requestTaskUpdate).mockResolvedValueOnce(taskDetailWithPermissions())
    const { task } = renderDialog({ watchers: [{ id: 41, name: 'Fabio Fini' }, { id: 31, name: 'Dario Dini' }] })

    fireEvent.click(screen.getByRole('button', { name: label('tasks.actions.requestUpdate.submit') }))

    await screen.findByRole('button', { name: label('tasks.actions.requestUpdate.submit') })
    expect(requestTaskUpdate).toHaveBeenCalledWith(task.id, { recipient_ids: [31, 32, 41] })
  })

  it('"Select all" deselects and reselects every candidate, and is indeterminate on a partial selection', () => {
    renderDialog()
    const selectAll = screen.getByRole('checkbox', { name: label('tasks.actions.requestUpdate.selectAll') })
    expect(selectAll).toBeChecked()

    fireEvent.click(selectAll)
    expect(screen.getByRole('checkbox', { name: /Dario Dini/ })).not.toBeChecked()
    expect(screen.getByRole('checkbox', { name: /Elsa Esposito/ })).not.toBeChecked()
    expect(screen.getByRole('checkbox', { name: /Fabio Fini/ })).not.toBeChecked()
    expect(selectAll).not.toBeChecked()

    fireEvent.click(screen.getByRole('checkbox', { name: /Dario Dini/ }))
    expect(selectAll).toHaveAttribute('aria-checked', 'mixed')

    fireEvent.click(selectAll)
    expect(screen.getByRole('checkbox', { name: /Dario Dini/ })).toBeChecked()
    expect(screen.getByRole('checkbox', { name: /Elsa Esposito/ })).toBeChecked()
    expect(screen.getByRole('checkbox', { name: /Fabio Fini/ })).toBeChecked()
  })
})

describe('TaskRequestUpdateDialog — client-side validation (AC-063)', () => {
  // REQUIREMENT CHANGED (spec 0126, D-7): candidates now start all selected, so
  // the empty-selection case is reached by deselecting all via "Select all"
  // rather than submitting an untouched (previously empty) form.
  it('rejects the submit with zero recipients selected, with no network call', async () => {
    renderDialog()

    fireEvent.click(screen.getByRole('checkbox', { name: label('tasks.actions.requestUpdate.selectAll') }))
    fireEvent.click(screen.getByRole('button', { name: label('tasks.actions.requestUpdate.submit') }))

    await screen.findByText(label('tasks.actions.requestUpdate.recipientsRequired'))
    expect(requestTaskUpdate).not.toHaveBeenCalled()
  })
})

describe('TaskRequestUpdateDialog — submit (D-11/D-12/D-14)', () => {
  // REQUIREMENT CHANGED (spec 0126, D-7): recipients start all selected, so
  // sending "only" one recipient now means deselecting the others first.
  it('sends only the checked recipients, omitting the blank message', async () => {
    vi.mocked(requestTaskUpdate).mockResolvedValueOnce(taskDetailWithPermissions())
    const { task, onOpenChange } = renderDialog()

    fireEvent.click(screen.getByRole('checkbox', { name: /Elsa Esposito/ }))
    fireEvent.click(screen.getByRole('checkbox', { name: /Fabio Fini/ }))
    fireEvent.click(screen.getByRole('button', { name: label('tasks.actions.requestUpdate.submit') }))

    await screen.findByRole('button', { name: label('tasks.actions.requestUpdate.submit') })
    expect(requestTaskUpdate).toHaveBeenCalledWith(task.id, { recipient_ids: [31] })
    expect(toast.success).toHaveBeenCalledWith(label('tasks.actions.requestUpdate.success'))
    expect(onOpenChange).toHaveBeenCalledWith(false)
  })

  it('trims and includes a non-blank message', async () => {
    vi.mocked(requestTaskUpdate).mockResolvedValueOnce(taskDetailWithPermissions())
    const { task } = renderDialog()

    fireEvent.click(screen.getByRole('checkbox', { name: /Dario Dini/ }))
    fireEvent.click(screen.getByRole('checkbox', { name: /Elsa Esposito/ }))
    fireEvent.change(screen.getByLabelText(label('tasks.actions.requestUpdate.message')), {
      target: { value: '  Fammi sapere a che punto sei  ' },
    })
    fireEvent.click(screen.getByRole('button', { name: label('tasks.actions.requestUpdate.submit') }))

    await screen.findByRole('button', { name: label('tasks.actions.requestUpdate.submit') })
    expect(requestTaskUpdate).toHaveBeenCalledWith(task.id, {
      recipient_ids: [41],
      message: 'Fammi sapere a che punto sei',
    })
  })
})

describe('TaskRequestUpdateDialog — server error mapping (AC-064)', () => {
  it('shows the "task bloccato" copy on a 409', async () => {
    vi.mocked(requestTaskUpdate).mockRejectedValueOnce(actionError(409))
    renderDialog()

    fireEvent.click(screen.getByRole('button', { name: label('tasks.actions.requestUpdate.submit') }))

    await screen.findByRole('button', { name: label('tasks.actions.requestUpdate.submit') })
    expect(toast.error).toHaveBeenCalledWith(label('tasks.actions.errors.blocked'))
  })

  it('shows the "fase sbagliata" copy on a plain 422 (isCompletable, no field errors)', async () => {
    vi.mocked(requestTaskUpdate).mockRejectedValueOnce(actionError(422))
    renderDialog()

    fireEvent.click(screen.getByRole('button', { name: label('tasks.actions.requestUpdate.submit') }))

    await screen.findByRole('button', { name: label('tasks.actions.requestUpdate.submit') })
    expect(toast.error).toHaveBeenCalledWith(label('tasks.actions.errors.wrongPhase'))
  })

  it('wires a field-scoped 422 on recipient_ids inline, without the generic toast', async () => {
    vi.mocked(requestTaskUpdate).mockRejectedValueOnce(
      actionError(422, { recipient_ids: ['Selected user is not an assignee or a watcher.'] }),
    )
    renderDialog()

    fireEvent.click(screen.getByRole('button', { name: label('tasks.actions.requestUpdate.submit') }))

    await screen.findByText('Selected user is not an assignee or a watcher.')
    expect(toast.error).not.toHaveBeenCalled()
  })
})
