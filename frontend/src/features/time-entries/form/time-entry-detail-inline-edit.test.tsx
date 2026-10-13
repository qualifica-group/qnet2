import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { TimeEntryDetail } from '@/features/time-entries/form/time-entry-detail'
import {
  buildEntry,
  defaultForSelect,
  field,
  label,
  openRow,
  pencilOf,
  timeEntryWrapper,
} from '@/features/time-entries/form/time-entry-test-fixtures'
import { fetchWorkOrderStages } from '@/features/work-orders/task-board/api'
import type { TimeEntry } from '@/features/time-entries/types'

/**
 * Spec 0226: the time entry detail edits in place, one row at a time. Each
 * save is the full PUT the endpoint expects (D-4), in which only the open
 * row's fields differ from the record.
 */

const updateTimeEntryMock = vi.fn()
const deleteTimeEntryMock = vi.fn()
vi.mock('@/features/time-entries/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/time-entries/api')>()),
  updateTimeEntry: (id: number, payload: unknown) => updateTimeEntryMock(id, payload),
  deleteTimeEntry: (id: number) => deleteTimeEntryMock(id),
}))

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/for-select/api')>()),
  fetchForSelect: (resource: string, params: unknown) => fetchForSelectMock(resource, params),
}))

vi.mock('@/features/tasks/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/tasks/api')>()),
  fetchTask: vi.fn().mockResolvedValue({ id: 40, title: 'Task Demo', registry_id: null, opportunity_id: null, work_order_id: null }),
}))

vi.mock('@/features/work-orders/task-board/api', () => ({ fetchWorkOrderStages: vi.fn() }))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('@/components/confirm-dialog-context', () => ({
  useConfirm: () => vi.fn().mockResolvedValue(true),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

function renderDetail(entry: TimeEntry, onDeleted = vi.fn()) {
  return render(<TimeEntryDetail entry={entry} onDeleted={onDeleted} />, { wrapper: timeEntryWrapper() })
}

const save = () => fireEvent.click(screen.getByRole('button', { name: label('common.inlineEdit.save') }))

/** The PUT body the unchanged entry of `buildEntry()` maps to. */
const UNCHANGED_PAYLOAD = {
  date: '2026-09-14',
  title: 'Rientro cliente',
  task_type_id: 1,
  minutes: 60,
  notes: null,
  registry_id: null,
  opportunity_id: null,
  work_order_id: null,
  task_id: null,
  work_order_stage_id: null,
}

async function savedPayload(): Promise<Record<string, unknown>> {
  await waitFor(() => expect(updateTimeEntryMock).toHaveBeenCalledTimes(1))
  const [id, payload] = updateTimeEntryMock.mock.calls[0] as [number, Record<string, unknown>]
  expect(id).toBe(1)
  return payload
}

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockImplementation(defaultForSelect)
  updateTimeEntryMock.mockReset()
  updateTimeEntryMock.mockImplementation(async () => buildEntry())
  deleteTimeEntryMock.mockReset()
  vi.mocked(fetchWorkOrderStages).mockReset()
  vi.mocked(fetchWorkOrderStages).mockResolvedValue([
    { id: 1, name: 'Analisi', sort_order: 0, closed_at: null, closed_by: null, logged_minutes: 0 },
  ])
})

describe('TimeEntryDetail — rows (AC-001, AC-002)', () => {
  it('shows the record on closed rows, with no edit form', () => {
    renderDetail(buildEntry({ notes: 'Nota iniziale' }))

    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    expect(screen.getByText('Nota iniziale')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: label('timeEntries.form.add') })).not.toBeInTheDocument()
  })

  it('Salva sends the full PUT with only the edited field changed', async () => {
    renderDetail(buildEntry())

    openRow('timeEntries.form.notes')
    fireEvent.change(field('timeEntries.form.notes'), { target: { value: 'Chiamata di follow-up' } })
    save()

    expect(await savedPayload()).toEqual({ ...UNCHANGED_PAYLOAD, notes: 'Chiamata di follow-up' })
  })

  it('Annulla and Esc restore the row without calling the API', () => {
    renderDetail(buildEntry())

    openRow('timeEntries.form.title')
    fireEvent.change(field('timeEntries.form.title'), { target: { value: 'Altro titolo' } })
    fireEvent.click(screen.getByRole('button', { name: label('common.inlineEdit.cancel') }))
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    expect(screen.queryByText('Altro titolo')).not.toBeInTheDocument()

    openRow('timeEntries.form.title')
    fireEvent.change(field('timeEntries.form.title'), { target: { value: 'Altro titolo' } })
    fireEvent.keyDown(field('timeEntries.form.title'), { key: 'Escape' })
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()

    expect(updateTimeEntryMock).not.toHaveBeenCalled()
  })

  it('the schedule row edits the times and the recomputed minutes together', async () => {
    renderDetail(buildEntry())

    openRow('timeEntries.form.scheduleRow')
    fireEvent.change(field('timeEntries.form.startTime'), { target: { value: '09:00' } })
    fireEvent.change(field('timeEntries.form.endTime'), { target: { value: '10:30' } })
    save()

    expect(await savedPayload()).toEqual({ ...UNCHANGED_PAYLOAD, start_time: '09:00', end_time: '10:30', minutes: 90 })
  })
})

describe('TimeEntryDetail — permissions and lock (AC-003)', () => {
  it('offers no pencil and no Elimina without the entry permissions', () => {
    renderDetail(buildEntry({ permissions: { update: false, delete: false } }))

    expect(pencilOf('timeEntries.form.title')).not.toBeInTheDocument()
    expect(pencilOf('timeEntries.form.notes')).not.toBeInTheDocument()
    expect(pencilOf('timeEntries.form.contextRow')).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: label('timeEntries.form.delete') })).not.toBeInTheDocument()
  })

  it('a linked task removes the title pencil only', () => {
    renderDetail(buildEntry({ task: { id: 40, title: 'Task Demo' } }))

    expect(pencilOf('timeEntries.form.title')).not.toBeInTheDocument()
    expect(pencilOf('timeEntries.form.notes')).toBeInTheDocument()
  })
})

describe('TimeEntryDetail — context row cascade (spec 0122 AC-031, spec 0163 AC-008)', () => {
  it('a new Cliente clears the opportunity and the work order', async () => {
    renderDetail(buildEntry({ opportunity: { id: 20, name: 'Opportunità X' } }))

    openRow('timeEntries.form.contextRow')
    fireEvent.click(screen.getByRole('combobox', { name: label('timeEntries.form.registry') }))
    fireEvent.click(await screen.findByRole('option', { name: 'ACME SpA' }))
    save()

    const payload = await savedPayload()
    expect(payload).toMatchObject({ registry_id: 10, opportunity_id: null, work_order_id: null })
  })

  it("a Commessa clears the Opportunita' and adopts its Cliente", async () => {
    fetchForSelectMock.mockImplementation(async (resource: string) =>
      resource === 'work-orders'
        ? {
            items: [{ id: 30, label: 'COM-0001', meta: { registry: { id: 77, name: 'Cliente Commessa' } } }],
            pagination: { offset: 0, limit: 25, total: 1 },
            export_link: null,
          }
        : defaultForSelect(resource),
    )
    renderDetail(buildEntry({ opportunity: { id: 20, name: 'Opportunità X' } }))

    openRow('timeEntries.form.contextRow')
    fireEvent.click(screen.getByRole('combobox', { name: label('timeEntries.form.workOrder') }))
    fireEvent.click(await screen.findByRole('option', { name: 'COM-0001' }))
    save()

    expect(await savedPayload()).toMatchObject({ work_order_id: 30, opportunity_id: null, registry_id: 77 })
  })

  it('changing the Commessa resets the fase instead of resubmitting the old one', async () => {
    renderDetail(
      buildEntry({
        work_order: { id: 30, code: 'COM-0001', title: '' },
        work_order_stage: { id: 1, name: 'Analisi' },
      }),
    )

    openRow('timeEntries.form.contextRow')
    expect(await screen.findByRole('combobox', { name: label('timeEntries.form.workOrderStage') })).toHaveTextContent(
      'Analisi',
    )
    fireEvent.click(screen.getByRole('combobox', { name: label('timeEntries.form.workOrder') }))
    fireEvent.click(await screen.findByRole('option', { name: 'COM-0002' }))
    save()

    expect(await savedPayload()).toMatchObject({ work_order_id: 31, work_order_stage_id: null })
  })
})

describe('TimeEntryDetail — delete (D-4)', () => {
  it('Elimina deletes after the confirmation and reports it', async () => {
    deleteTimeEntryMock.mockResolvedValue(undefined)
    const onDeleted = vi.fn()
    renderDetail(buildEntry(), onDeleted)

    fireEvent.click(screen.getByRole('button', { name: label('timeEntries.form.delete') }))

    await waitFor(() => expect(deleteTimeEntryMock).toHaveBeenCalledWith(1))
    await waitFor(() => expect(onDeleted).toHaveBeenCalled())
  })
})
