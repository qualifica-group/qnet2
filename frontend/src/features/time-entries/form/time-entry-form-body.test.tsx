import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { AxiosError, AxiosHeaders } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { TimeEntryFormBody } from '@/features/time-entries/form/time-entry-form-body'
import type { TimeEntryFormMode } from '@/features/time-entries/form/use-time-entry-form'
import { fetchWorkOrderStages } from '@/features/work-orders/task-board/api'
import type { TaskDetailWithPermissions } from '@/features/tasks/types'
import type { TimeEntry } from '@/features/time-entries/types'

const createTimeEntryMock = vi.fn()
const updateTimeEntryMock = vi.fn()
vi.mock('@/features/time-entries/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/time-entries/api')>(
    '@/features/time-entries/api',
  )
  return {
    ...actual,
    createTimeEntry: (payload: unknown) => createTimeEntryMock(payload),
    updateTimeEntry: (id: number, payload: unknown) => updateTimeEntryMock(id, payload),
  }
})

const fetchTaskMock = vi.fn()
vi.mock('@/features/tasks/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/tasks/api')>('@/features/tasks/api')
  return { ...actual, fetchTask: (id: number) => fetchTaskMock(id) }
})

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>(
    '@/features/for-select/api',
  )
  return {
    ...actual,
    fetchForSelect: (resource: string, params: unknown) => fetchForSelectMock(resource, params),
  }
})

vi.mock('@/features/work-orders/task-board/api', () => ({ fetchWorkOrderStages: vi.fn() }))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

// `TimeEntryFormActions` calls `useConfirm()`: auto-confirm so reset/delete
// flows do not depend on the real dialog UI.
vi.mock('@/components/confirm-dialog-context', () => ({
  useConfirm: () => vi.fn().mockResolvedValue(true),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const EMPTY_PAGE = { items: [], pagination: { offset: 0, limit: 25, total: 0 }, export_link: null }
const TASK_TYPES_PAGE = {
  items: [
    { id: 1, label: 'Attività', meta: { color: 'blue', icon: 'clipboard-list' } },
    { id: 2, label: 'Riunione', meta: { color: 'violet', icon: 'users' } },
  ],
  pagination: { offset: 0, limit: 25, total: 2 },
  export_link: null,
}
const REGISTRIES_PAGE = {
  items: [{ id: 10, label: 'ACME SpA' }],
  pagination: { offset: 0, limit: 25, total: 1 },
  export_link: null,
}
const TASKS_PAGE = {
  items: [{ id: 40, label: 'Task Demo', meta: {} }],
  pagination: { offset: 0, limit: 25, total: 1 },
  export_link: null,
}
const WORK_ORDER_PAGE = {
  items: [
    { id: 30, label: 'COM-0001', meta: {} },
    { id: 31, label: 'COM-0002', meta: {} },
  ],
  pagination: { offset: 0, limit: 25, total: 2 },
  export_link: null,
}

function buildEntry(overrides: Partial<TimeEntry> = {}): TimeEntry {
  return {
    id: 1,
    user: { id: 1, name: 'Utente Corrente' },
    date: '2026-09-14',
    title: 'Rientro cliente',
    task_type: { id: 1, name: 'Attività', color: 'blue', icon: 'clipboard-list' },
    start_time: null,
    end_time: null,
    minutes: 60,
    notes: null,
    registry: null,
    opportunity: null,
    work_order: null,
    task: null,
    work_order_stage: null,
    created_at: '2026-09-14T08:00:00Z',
    updated_at: '2026-09-14T08:00:00Z',
    permissions: { update: true, delete: true },
    ...overrides,
  }
}

/** A 422 shaped so `axios.isAxiosError` recognizes it (mirrors `task-detail.test.tsx`). */
function validationError(errors: Record<string, string[]>): AxiosError {
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

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function renderBody(mode: TimeEntryFormMode, onSuccess = vi.fn()) {
  return render(
    <TimeEntryFormBody mode={mode} onSuccess={onSuccess} onCancel={vi.fn()} headerTitle="Nuovo segnatempo" />,
    { wrapper: wrapper() },
  )
}

const label = (key: string) => i18n.t(key)
/** "Aggiungi" on create, "Aggiorna" on edit — the submit label is mode-dependent. */
const submitButton = (mode: 'create' | 'edit' = 'create') =>
  screen.getByRole('button', { name: label(mode === 'edit' ? 'timeEntries.form.update' : 'timeEntries.form.add') })
/**
 * Anchored regex match: a required field's `FormLabel` also renders a
 * trailing "*", so a plain exact match would miss it — but `{exact: false}`
 * (substring) is unsafe here, since "Alle ore" is itself a substring of
 * "D**alle** ore" and would resolve to both fields. The regex tolerates the
 * optional "*" while still anchoring the whole string.
 */
function escapeRegExp(value: string): string {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}
const field = (key: string) => screen.getByLabelText(new RegExp(`^${escapeRegExp(label(key))}\\s*\\*?$`))

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockImplementation(async (resource: string) => {
    if (resource === 'task-types') return TASK_TYPES_PAGE
    if (resource === 'registries') return REGISTRIES_PAGE
    if (resource === 'tasks') return TASKS_PAGE
    if (resource === 'work-orders') return WORK_ORDER_PAGE
    return EMPTY_PAGE
  })
  createTimeEntryMock.mockReset()
  updateTimeEntryMock.mockReset()
  fetchTaskMock.mockReset()
  vi.mocked(fetchWorkOrderStages).mockReset()
  vi.mocked(fetchWorkOrderStages).mockResolvedValue([
    { id: 1, name: 'Analisi', sort_order: 0, closed_at: null, closed_by: null, logged_minutes: 0 },
  ])
})

describe('TimeEntryFormBody — minutes computed from the times (AC-029)', () => {
  it('fills "Tempo" from start/end, keeps a manual override until a time actually changes', async () => {
    renderBody({ type: 'create' })

    const start = field('timeEntries.form.startTime')
    const end = field('timeEntries.form.endTime')
    const minutes = field('timeEntries.form.minutes')

    fireEvent.change(start, { target: { value: '09:00' } })
    fireEvent.change(end, { target: { value: '10:30' } })
    expect(minutes).toHaveValue('01:30')

    fireEvent.change(minutes, { target: { value: '03:00' } })
    expect(minutes).toHaveValue('03:00')

    // end <= start: no recompute, the manual override survives.
    fireEvent.change(end, { target: { value: '08:00' } })
    expect(minutes).toHaveValue('03:00')

    // A real time change recomputes and wins over the override.
    fireEvent.change(end, { target: { value: '09:45' } })
    expect(minutes).toHaveValue('00:45')
  })
})

describe('TimeEntryFormBody — invalid submit (AC-030)', () => {
  it('shows field errors, wires the aria triad and makes no API call', async () => {
    renderBody({ type: 'create' })

    fireEvent.click(submitButton())

    expect(await screen.findByText(label('timeEntries.form.titleRequired'))).toBeInTheDocument()
    expect(screen.getByText(label('timeEntries.form.typeRequired'))).toBeInTheDocument()
    expect(screen.getByText(label('timeEntries.form.minutesRequired'))).toBeInTheDocument()

    const titleInput = field('timeEntries.form.title')
    expect(titleInput).toHaveAttribute('aria-invalid', 'true')
    expect(titleInput).toHaveAttribute('aria-describedby')

    expect(createTimeEntryMock).not.toHaveBeenCalled()
  })
})

describe('TimeEntryFormBody — D-5 cascade (AC-031)', () => {
  it('clears opportunity and work order when the client changes', async () => {
    updateTimeEntryMock.mockResolvedValue(buildEntry())
    renderBody({
      type: 'edit',
      entry: buildEntry({ opportunity: { id: 20, name: 'Opportunità X' } }),
    })

    fireEvent.click(screen.getByRole('combobox', { name: label('timeEntries.form.registry') }))
    fireEvent.click(await screen.findByRole('option', { name: 'ACME SpA' }))

    fireEvent.click(submitButton('edit'))

    await waitFor(() => expect(updateTimeEntryMock).toHaveBeenCalled())
    const [, payload] = updateTimeEntryMock.mock.calls[0] as [number, Record<string, unknown>]
    expect(payload.registry_id).toBe(10)
    expect(payload.opportunity_id).toBeNull()
    expect(payload.work_order_id).toBeNull()
  })

  it('picking a Commessa clears Opportunita\' and adopts its Cliente when the option carries one', async () => {
    fetchForSelectMock.mockImplementation(async (resource: string) => {
      if (resource === 'task-types') return TASK_TYPES_PAGE
      if (resource === 'work-orders') {
        return {
          items: [{ id: 30, label: 'COM-0001', meta: { registry: { id: 77, name: 'Cliente Commessa' } } }],
          pagination: { offset: 0, limit: 25, total: 1 },
          export_link: null,
        }
      }
      return EMPTY_PAGE
    })
    updateTimeEntryMock.mockResolvedValue(buildEntry())
    renderBody({
      type: 'edit',
      entry: buildEntry({ opportunity: { id: 20, name: 'Opportunità X' } }),
    })

    fireEvent.click(screen.getByRole('combobox', { name: label('timeEntries.form.workOrder') }))
    fireEvent.click(await screen.findByRole('option', { name: 'COM-0001' }))

    fireEvent.click(submitButton('edit'))

    await waitFor(() => expect(updateTimeEntryMock).toHaveBeenCalled())
    const [, payload] = updateTimeEntryMock.mock.calls[0] as [number, Record<string, unknown>]
    expect(payload.work_order_id).toBe(30)
    expect(payload.opportunity_id).toBeNull()
    expect(payload.registry_id).toBe(77)
  })

  it('locks title and links to the picked Task\'s own values', async () => {
    fetchTaskMock.mockResolvedValue({
      id: 40,
      title: 'Task Demo',
      registry_id: 88,
      registry: { id: 88, name: 'Cliente Task' },
      opportunity_id: null,
      opportunity: null,
      work_order_id: null,
      work_order: null,
    } as unknown as TaskDetailWithPermissions)

    renderBody({ type: 'create' })

    fireEvent.click(screen.getByRole('combobox', { name: label('timeEntries.form.activity') }))
    fireEvent.click(await screen.findByRole('option', { name: 'Task Demo' }))

    await waitFor(() => expect(fetchTaskMock).toHaveBeenCalledWith(40))

    const titleInput = await screen.findByDisplayValue('Task Demo')
    expect(titleInput).toBeDisabled()

    const registryPicker = screen.getByRole('combobox', { name: label('timeEntries.form.registry') })
    expect(registryPicker).toBeDisabled()
    expect(registryPicker).toHaveTextContent('Cliente Task')
  })
})

describe('TimeEntryFormBody — "Fase" (spec 0163 AC-008)', () => {
  it('sends the picked fase in the create payload', async () => {
    createTimeEntryMock.mockResolvedValue(buildEntry())
    renderBody({ type: 'create' })

    fireEvent.change(field('timeEntries.form.title'), { target: { value: 'Rientro cliente' } })
    fireEvent.click(await screen.findByRole('radio', { name: 'Attività' }))
    fireEvent.change(field('timeEntries.form.minutes'), { target: { value: '01:00' } })

    fireEvent.click(screen.getByRole('combobox', { name: label('timeEntries.form.workOrder') }))
    fireEvent.click(await screen.findByRole('option', { name: 'COM-0001' }))

    const fasePicker = await screen.findByRole('combobox', { name: label('timeEntries.form.workOrderStage') })
    await waitFor(() => expect(fasePicker).not.toBeDisabled())
    fireEvent.click(fasePicker)
    fireEvent.click(await screen.findByRole('option', { name: 'Analisi' }))

    fireEvent.click(submitButton())

    await waitFor(() => expect(createTimeEntryMock).toHaveBeenCalled())
    const [payload] = createTimeEntryMock.mock.calls[0] as [Record<string, unknown>]
    expect(payload.work_order_stage_id).toBe(1)
  })

  it('resets the fase when the commessa changes, without resubmitting the old one', async () => {
    updateTimeEntryMock.mockResolvedValue(buildEntry())
    renderBody({
      type: 'edit',
      entry: buildEntry({
        work_order: { id: 30, code: 'COM-0001', title: '' },
        work_order_stage: { id: 1, name: 'Analisi' },
      }),
    })

    await waitFor(() =>
      expect(screen.getByRole('combobox', { name: label('timeEntries.form.workOrder') })).toHaveTextContent('COM-0001'),
    )
    expect(await screen.findByRole('combobox', { name: label('timeEntries.form.workOrderStage') })).toHaveTextContent(
      'Analisi',
    )

    fireEvent.click(screen.getByRole('combobox', { name: label('timeEntries.form.workOrder') }))
    fireEvent.click(await screen.findByRole('option', { name: 'COM-0002' }))

    fireEvent.click(submitButton('edit'))

    await waitFor(() => expect(updateTimeEntryMock).toHaveBeenCalled())
    const [, payload] = updateTimeEntryMock.mock.calls[0] as [number, Record<string, unknown>]
    expect(payload.work_order_id).toBe(31)
    expect(payload.work_order_stage_id).toBeNull()
  })

  it('does not show or send a fase once a task is linked', async () => {
    createTimeEntryMock.mockResolvedValue(buildEntry())
    fetchTaskMock.mockResolvedValue({
      id: 40,
      title: 'Task Demo',
      registry_id: null,
      registry: null,
      opportunity_id: null,
      opportunity: null,
      work_order_id: 30,
      work_order: { id: 30, code: 'COM-0001', title: '' },
    } as unknown as TaskDetailWithPermissions)

    renderBody({ type: 'create' })

    fireEvent.click(screen.getByRole('combobox', { name: label('timeEntries.form.activity') }))
    fireEvent.click(await screen.findByRole('option', { name: 'Task Demo' }))

    await waitFor(() => expect(fetchTaskMock).toHaveBeenCalledWith(40))
    expect(screen.queryByRole('combobox', { name: label('timeEntries.form.workOrderStage') })).not.toBeInTheDocument()

    fireEvent.click(await screen.findByRole('radio', { name: 'Attività' }))
    fireEvent.change(field('timeEntries.form.minutes'), { target: { value: '01:00' } })
    fireEvent.click(submitButton())

    await waitFor(() => expect(createTimeEntryMock).toHaveBeenCalled())
    const [payload] = createTimeEntryMock.mock.calls[0] as [Record<string, unknown>]
    expect(payload.work_order_stage_id).toBeUndefined()
  })
})

describe('TimeEntryFormBody — create/update contract', () => {
  it('sends the create payload matching the frozen contract, including the caller-provided user_id', async () => {
    createTimeEntryMock.mockResolvedValue(buildEntry())
    const onSuccess = vi.fn()
    renderBody({ type: 'create', userId: 5 }, onSuccess)

    fireEvent.change(field('timeEntries.form.title'), {
      target: { value: 'Rientro cliente' },
    })
    fireEvent.click(await screen.findByRole('radio', { name: 'Attività' }))
    fireEvent.change(field('timeEntries.form.minutes'), {
      target: { value: '01:00' },
    })

    fireEvent.click(submitButton())

    await waitFor(() => expect(createTimeEntryMock).toHaveBeenCalled())
    const [payload] = createTimeEntryMock.mock.calls[0] as [Record<string, unknown>]
    expect(payload).toMatchObject({ title: 'Rientro cliente', task_type_id: 1, minutes: 60, user_id: 5 })
    expect(onSuccess).toHaveBeenCalled()
  })

  it('maps a 422 onto the matching field instead of a generic toast', async () => {
    createTimeEntryMock.mockRejectedValue(validationError({ minutes: ['I minuti devono essere tra 1 e 1440.'] }))
    renderBody({ type: 'create' })

    fireEvent.change(field('timeEntries.form.title'), { target: { value: 'X' } })
    fireEvent.click(await screen.findByRole('radio', { name: 'Attività' }))
    fireEvent.change(field('timeEntries.form.minutes'), {
      target: { value: '01:00' },
    })
    fireEvent.click(submitButton())

    expect(await screen.findByText('I minuti devono essere tra 1 e 1440.')).toBeInTheDocument()
  })
})
