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

/**
 * Fills and submits the mandatory message, then presses submit — the shared
 * arrange step every submit test needs. Matched by role/regex, not exact
 * label text: the label now carries a trailing required-marker glyph.
 */
function fillMessageAndSubmit(message = 'a che punto sei?') {
  fireEvent.change(screen.getByRole('textbox', { name: /Message/ }), {
    target: { value: message },
  })
  fireEvent.click(screen.getByRole('button', { name: label('tasks.actions.requestUpdate.submit') }))
}

/**
 * The radio's accessible name is `${label} (${count})` (component's own
 * `aria-label`, since "Assignees" and "Assignees and watchers" would
 * otherwise collide as substrings of one another).
 */
function targetRadio(labelKey: string, count: number) {
  return screen.getByRole('radio', { name: `${label(labelKey)} (${count})` })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.mocked(requestTaskUpdate).mockReset()
  vi.mocked(toast.success).mockReset()
  vi.mocked(toast.error).mockReset()
})

describe('TaskRequestUpdateDialog — target options and recipient counts (spec 0153 D-14, AC-018)', () => {
  it('shows the three target options with counts, "assignees" selected by default', () => {
    renderDialog()

    const assignees = targetRadio('tasks.actions.requestUpdate.targetAssignees', 2)
    const observers = targetRadio('tasks.actions.requestUpdate.targetObservers', 1)
    const all = targetRadio('tasks.actions.requestUpdate.targetAll', 3)

    expect(assignees).toBeChecked()
    expect(observers).not.toBeChecked()
    expect(all).not.toBeChecked()
  })

  it('states that watchers get a copy under the "assignees" option', () => {
    renderDialog()

    expect(screen.getByText(label('tasks.actions.requestUpdate.targetAssigneesHint'))).toBeInTheDocument()
  })

  it('disables a target option with zero recipients', () => {
    renderDialog({ watchers: [] })

    expect(targetRadio('tasks.actions.requestUpdate.targetObservers', 0)).toBeDisabled()
  })

  it('counts "all" as the deduplicated union of assignees and watchers', () => {
    renderDialog({
      assignees: [{ id: 31, name: 'Dario Dini' }],
      watchers: [{ id: 31, name: 'Dario Dini' }, { id: 41, name: 'Fabio Fini' }],
    })

    expect(targetRadio('tasks.actions.requestUpdate.targetAll', 2)).toBeInTheDocument()
  })

  it('shows the empty state and disables submit when the task has no assignees nor watchers', () => {
    renderDialog({ assignees: [], watchers: [] })

    expect(screen.getByText(label('tasks.actions.requestUpdate.recipientsEmpty'))).toBeInTheDocument()
    expect(screen.getByRole('button', { name: label('tasks.actions.requestUpdate.submit') })).toBeDisabled()
  })

  it('switches the selected target when another enabled option is picked', () => {
    renderDialog()

    fireEvent.click(targetRadio('tasks.actions.requestUpdate.targetObservers', 1))

    expect(targetRadio('tasks.actions.requestUpdate.targetObservers', 1)).toBeChecked()
    expect(targetRadio('tasks.actions.requestUpdate.targetAssignees', 2)).not.toBeChecked()
  })
})

describe('TaskRequestUpdateDialog — mandatory message (spec 0153 D-14)', () => {
  it('rejects the submit with a blank message, with no network call', async () => {
    renderDialog()

    fireEvent.click(screen.getByRole('button', { name: label('tasks.actions.requestUpdate.submit') }))

    await screen.findByText(label('tasks.actions.requestUpdate.messageTooShort'))
    expect(requestTaskUpdate).not.toHaveBeenCalled()
  })

  it('rejects a message under 3 characters', async () => {
    renderDialog()
    fillMessageAndSubmit('hi')

    await screen.findByText(label('tasks.actions.requestUpdate.messageTooShort'))
    expect(requestTaskUpdate).not.toHaveBeenCalled()
  })
})

describe('TaskRequestUpdateDialog — submit (spec 0153 D-14)', () => {
  it('sends the default target (assignees) with the trimmed message', async () => {
    vi.mocked(requestTaskUpdate).mockResolvedValueOnce(taskDetailWithPermissions())
    const { task, onOpenChange } = renderDialog()

    fillMessageAndSubmit('  Fammi sapere a che punto sei  ')

    await screen.findByRole('button', { name: label('tasks.actions.requestUpdate.submit') })
    expect(requestTaskUpdate).toHaveBeenCalledWith(task.id, {
      target: 'assignees',
      message: 'Fammi sapere a che punto sei',
    })
    expect(toast.success).toHaveBeenCalledWith(label('tasks.actions.requestUpdate.success'))
    expect(onOpenChange).toHaveBeenCalledWith(false)
  })

  it('sends the chosen target ("all")', async () => {
    vi.mocked(requestTaskUpdate).mockResolvedValueOnce(taskDetailWithPermissions())
    const { task } = renderDialog()

    fireEvent.click(targetRadio('tasks.actions.requestUpdate.targetAll', 3))
    fillMessageAndSubmit()

    await screen.findByRole('button', { name: label('tasks.actions.requestUpdate.submit') })
    expect(requestTaskUpdate).toHaveBeenCalledWith(task.id, { target: 'all', message: 'a che punto sei?' })
  })
})

describe('TaskRequestUpdateDialog — server error mapping (spec 0153 D-14)', () => {
  it('shows the "task bloccato" copy on a 409', async () => {
    vi.mocked(requestTaskUpdate).mockRejectedValueOnce(actionError(409))
    renderDialog()

    fillMessageAndSubmit()

    await screen.findByRole('button', { name: label('tasks.actions.requestUpdate.submit') })
    expect(toast.error).toHaveBeenCalledWith(label('tasks.actions.errors.blocked'))
  })

  it('shows the "fase sbagliata" copy on a plain 422 (no field errors)', async () => {
    vi.mocked(requestTaskUpdate).mockRejectedValueOnce(actionError(422))
    renderDialog()

    fillMessageAndSubmit()

    await screen.findByRole('button', { name: label('tasks.actions.requestUpdate.submit') })
    expect(toast.error).toHaveBeenCalledWith(label('tasks.actions.errors.wrongPhase'))
  })

  it('wires a field-scoped 422 on message inline, without the generic toast', async () => {
    vi.mocked(requestTaskUpdate).mockRejectedValueOnce(
      actionError(422, { message: ['The message must be at least 3 characters.'] }),
    )
    renderDialog()

    fillMessageAndSubmit()

    await screen.findByText('The message must be at least 3 characters.')
    expect(toast.error).not.toHaveBeenCalled()
  })
})
