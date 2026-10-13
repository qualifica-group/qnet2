import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { TimeEntryCreateForm } from '@/features/time-entries/form/time-entry-create-form'
import {
  buildEntry,
  defaultForSelect,
  field,
  label,
  openRow,
  pencilOf,
  timeEntryWrapper,
  validationError,
} from '@/features/time-entries/form/time-entry-test-fixtures'
import { fetchWorkOrderStages } from '@/features/work-orders/task-board/api'
import type { TaskDetailWithPermissions } from '@/features/tasks/types'

/**
 * Spec 0226 D-6: the create form is the detail's replica with its rows closed;
 * Fatto/Ripristina per row, "Aggiungi" validates and creates everything with
 * the payload of spec 0122.
 */

const createTimeEntryMock = vi.fn()
vi.mock('@/features/time-entries/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/time-entries/api')>()),
  createTimeEntry: (payload: unknown) => createTimeEntryMock(payload),
}))

const fetchTaskMock = vi.fn()
vi.mock('@/features/tasks/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/tasks/api')>()),
  fetchTask: (id: number) => fetchTaskMock(id),
}))

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/for-select/api')>()),
  fetchForSelect: (resource: string, params: unknown) => fetchForSelectMock(resource, params),
}))

vi.mock('@/features/work-orders/task-board/api', () => ({ fetchWorkOrderStages: vi.fn() }))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('@/components/confirm-dialog-context', () => ({
  useConfirm: () => vi.fn().mockResolvedValue(true),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

function renderCreate(props: { userId?: number; onSuccess?: () => void } = {}) {
  return render(<TimeEntryCreateForm onSuccess={props.onSuccess ?? vi.fn()} onCancel={vi.fn()} userId={props.userId} />, {
    wrapper: timeEntryWrapper(),
  })
}

const submit = () => fireEvent.click(screen.getByRole('button', { name: label('timeEntries.form.add') }))
const apply = () => fireEvent.click(screen.getByRole('button', { name: label('common.inlineEdit.apply') }))

/** Title, type and time: the three required rows of a minimal valid draft. */
async function fillRequired(title = 'Rientro cliente') {
  openRow('timeEntries.form.title')
  fireEvent.change(field('timeEntries.form.title'), { target: { value: title } })
  apply()
  openRow('timeEntries.form.type')
  fireEvent.click(await screen.findByRole('radio', { name: 'Attività' }))
  apply()
  openRow('timeEntries.form.scheduleRow')
  fireEvent.change(field('timeEntries.form.minutes'), { target: { value: '01:00' } })
  apply()
}

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockImplementation(defaultForSelect)
  createTimeEntryMock.mockReset()
  fetchTaskMock.mockReset()
  vi.mocked(fetchWorkOrderStages).mockReset()
  vi.mocked(fetchWorkOrderStages).mockResolvedValue([
    { id: 1, name: 'Analisi', sort_order: 0, closed_at: null, closed_by: null, logged_minutes: 0 },
  ])
})

describe('TimeEntryCreateForm — closed rows (AC-004)', () => {
  it('starts with every row closed and keeps a confirmed value on its closed row', async () => {
    renderCreate()

    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    expect(pencilOf('timeEntries.form.contextRow')).toBeInTheDocument()

    openRow('timeEntries.form.contextRow')
    fireEvent.click(screen.getByRole('combobox', { name: label('timeEntries.form.registry') }))
    fireEvent.click(await screen.findByRole('option', { name: 'ACME SpA' }))
    apply()

    expect(await screen.findByText('ACME SpA')).toBeInTheDocument()
    expect(screen.queryByRole('combobox', { name: label('timeEntries.form.registry') })).not.toBeInTheDocument()
  })

  it('Ripristina puts the row back as it was when opened', () => {
    renderCreate()

    openRow('timeEntries.form.title')
    fireEvent.change(field('timeEntries.form.title'), { target: { value: 'Bozza' } })
    fireEvent.click(screen.getByRole('button', { name: label('common.inlineEdit.revert') }))

    expect(screen.queryByText('Bozza')).not.toBeInTheDocument()
  })
})

describe('TimeEntryCreateForm — minutes computed from the times (spec 0122 AC-029)', () => {
  it('fills "Tempo" from start/end, keeps a manual override until a time actually changes', () => {
    renderCreate()
    openRow('timeEntries.form.scheduleRow')

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

describe('TimeEntryCreateForm — invalid submit (spec 0122 AC-030)', () => {
  it('shows the errors on the closed rows, wires the aria triad and makes no API call', async () => {
    renderCreate()

    submit()

    expect(await screen.findByText(label('timeEntries.form.titleRequired'))).toBeInTheDocument()
    expect(screen.getByText(label('timeEntries.form.typeRequired'))).toBeInTheDocument()
    expect(screen.getByText(label('timeEntries.form.minutesRequired'))).toBeInTheDocument()

    openRow('timeEntries.form.title')
    const titleInput = field('timeEntries.form.title')
    expect(titleInput).toHaveAttribute('aria-invalid', 'true')
    expect(titleInput).toHaveAttribute('aria-describedby')

    expect(createTimeEntryMock).not.toHaveBeenCalled()
  })
})

describe('TimeEntryCreateForm — linked task (spec 0122 AC-031, spec 0226 D-3)', () => {
  it('locks title and links to the picked task: the title row loses its pencil', async () => {
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
    renderCreate()

    openRow('timeEntries.form.contextRow')
    fireEvent.click(screen.getByRole('combobox', { name: label('timeEntries.form.activity') }))
    fireEvent.click(await screen.findByRole('option', { name: 'Task Demo' }))
    await waitFor(() => expect(fetchTaskMock).toHaveBeenCalledWith(40))

    const registryPicker = screen.getByRole('combobox', { name: label('timeEntries.form.registry') })
    expect(registryPicker).toBeDisabled()
    await waitFor(() => expect(registryPicker).toHaveTextContent('Cliente Task'))

    apply()
    expect(pencilOf('timeEntries.form.title')).not.toBeInTheDocument()
    expect(screen.getAllByText('Task Demo').length).toBeGreaterThan(0)
  })
})

describe('TimeEntryCreateForm — "Fase" (spec 0163 AC-008)', () => {
  it('sends the picked fase in the create payload', async () => {
    createTimeEntryMock.mockResolvedValue(buildEntry())
    renderCreate()
    await fillRequired()

    openRow('timeEntries.form.contextRow')
    fireEvent.click(screen.getByRole('combobox', { name: label('timeEntries.form.workOrder') }))
    fireEvent.click(await screen.findByRole('option', { name: 'COM-0001' }))
    const fasePicker = await screen.findByRole('combobox', { name: label('timeEntries.form.workOrderStage') })
    await waitFor(() => expect(fasePicker).not.toBeDisabled())
    fireEvent.click(fasePicker)
    fireEvent.click(await screen.findByRole('option', { name: 'Analisi' }))

    // Aggiungi confirms the open row first.
    submit()

    await waitFor(() => expect(createTimeEntryMock).toHaveBeenCalled())
    const [payload] = createTimeEntryMock.mock.calls[0] as [Record<string, unknown>]
    expect(payload.work_order_stage_id).toBe(1)
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
    renderCreate()

    openRow('timeEntries.form.contextRow')
    fireEvent.click(screen.getByRole('combobox', { name: label('timeEntries.form.activity') }))
    fireEvent.click(await screen.findByRole('option', { name: 'Task Demo' }))
    await waitFor(() => expect(fetchTaskMock).toHaveBeenCalledWith(40))
    expect(screen.queryByRole('combobox', { name: label('timeEntries.form.workOrderStage') })).not.toBeInTheDocument()
    apply()

    openRow('timeEntries.form.type')
    fireEvent.click(await screen.findByRole('radio', { name: 'Attività' }))
    apply()
    openRow('timeEntries.form.scheduleRow')
    fireEvent.change(field('timeEntries.form.minutes'), { target: { value: '01:00' } })
    submit()

    await waitFor(() => expect(createTimeEntryMock).toHaveBeenCalled())
    const [payload] = createTimeEntryMock.mock.calls[0] as [Record<string, unknown>]
    expect(payload.work_order_stage_id).toBeUndefined()
  })
})

describe('TimeEntryCreateForm — create contract', () => {
  it('sends the payload of spec 0122, including the caller-provided user_id', async () => {
    createTimeEntryMock.mockResolvedValue(buildEntry())
    const onSuccess = vi.fn()
    renderCreate({ userId: 5, onSuccess })
    await fillRequired()

    submit()

    await waitFor(() => expect(createTimeEntryMock).toHaveBeenCalled())
    const [payload] = createTimeEntryMock.mock.calls[0] as [Record<string, unknown>]
    expect(payload).toMatchObject({ title: 'Rientro cliente', task_type_id: 1, minutes: 60, user_id: 5 })
    expect(onSuccess).toHaveBeenCalled()
  })

  it('maps a 422 onto the matching closed row instead of a generic error', async () => {
    createTimeEntryMock.mockRejectedValue(validationError({ minutes: ['I minuti devono essere tra 1 e 1440.'] }))
    renderCreate()
    await fillRequired('X')

    submit()

    expect(await screen.findByText('I minuti devono essere tra 1 e 1440.')).toHaveAttribute('role', 'alert')
  })

})
