import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { TaskTimeEntriesSection } from '@/features/time-entries/task/task-time-entries-section'
import { getTodayDateKey, toDateString } from '@/features/time-entries/time-entry-period'
import type { TaskTimeEntriesResponse, TimeEntry } from '@/features/time-entries/types'

/*
 * "Segnatempo" tab body of the Task detail (spec 0122 MT-F6/D-9, AC-040).
 */

const fetchTaskTimeEntriesMock = vi.fn()
const createTaskTimeEntryMock = vi.fn()
const deleteTimeEntryMock = vi.fn()
const fetchTimeEntryMock = vi.fn()
vi.mock('@/features/time-entries/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/time-entries/api')>(
    '@/features/time-entries/api',
  )
  return {
    ...actual,
    fetchTaskTimeEntries: (taskId: number) => fetchTaskTimeEntriesMock(taskId),
    createTaskTimeEntry: (taskId: number, payload: unknown) => createTaskTimeEntryMock(taskId, payload),
    deleteTimeEntry: (id: number) => deleteTimeEntryMock(id),
    fetchTimeEntry: (id: number) => fetchTimeEntryMock(id),
  }
})

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>(
    '@/features/for-select/api',
  )
  return { ...actual, fetchForSelect: (resource: string, params: unknown) => fetchForSelectMock(resource, params) }
})

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const TASK_TYPES_PAGE = {
  items: [
    { id: 1, label: 'Attività', meta: { color: 'blue', icon: 'clipboard-list' } },
    { id: 2, label: 'Riunione', meta: { color: 'violet', icon: 'users' } },
  ],
  pagination: { offset: 0, limit: 25, total: 2 },
  export_link: null,
}
const EMPTY_PAGE = { items: [], pagination: { offset: 0, limit: 25, total: 0 }, export_link: null }

const TASK_ID = 7

function buildEntry(overrides: Partial<TimeEntry> & { id: number }): TimeEntry {
  return {
    date: getTodayDateKey(),
    user: { id: 1, name: 'Mario Rossi' },
    title: 'Task Demo',
    task_type: { id: 1, name: 'Attività', color: 'blue', icon: 'clipboard-list' },
    start_time: null,
    end_time: null,
    minutes: 30,
    notes: null,
    registry: null,
    opportunity: null,
    work_order: null,
    task: { id: TASK_ID, title: 'Task Demo' },
    work_order_stage: null,
    created_at: '2026-09-14T08:00:00Z',
    updated_at: '2026-09-14T08:00:00Z',
    permissions: { update: true, delete: true },
    ...overrides,
  }
}

function yesterdayKey(): string {
  const date = new Date()
  date.setDate(date.getDate() - 1)
  return toDateString(date)
}

function buildResponse(overrides: Partial<TaskTimeEntriesResponse> = {}): TaskTimeEntriesResponse {
  const items = overrides.items ?? []
  return {
    total_minutes: items.reduce((sum, entry) => sum + entry.minutes, 0),
    can_create: true,
    items,
    ...overrides,
  }
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

const label = (key: string) => i18n.t(key)

beforeEach(() => {
  fetchTaskTimeEntriesMock.mockReset()
  createTaskTimeEntryMock.mockReset()
  deleteTimeEntryMock.mockReset()
  fetchTimeEntryMock.mockReset()
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockImplementation(async (resource: string) => {
    if (resource === 'task-types') return TASK_TYPES_PAGE
    return EMPTY_PAGE
  })
})

describe('TaskTimeEntriesSection — editor gating (AC-040)', () => {
  it('shows the editor without title/context fields when can_create is true', async () => {
    fetchTaskTimeEntriesMock.mockResolvedValue(buildResponse({ can_create: true, items: [] }))
    render(<TaskTimeEntriesSection taskId={TASK_ID} />, { wrapper: wrapper() })

    expect(await screen.findByRole('button', { name: label('timeEntries.task.addButton') })).toBeInTheDocument()
    expect(screen.queryByLabelText(label('timeEntries.form.title'))).not.toBeInTheDocument()
    expect(screen.queryByRole('combobox', { name: label('timeEntries.form.registry') })).not.toBeInTheDocument()
  })

  it('hides the editor and shows the forbidden message when can_create is false', async () => {
    fetchTaskTimeEntriesMock.mockResolvedValue(buildResponse({ can_create: false, items: [] }))
    render(<TaskTimeEntriesSection taskId={TASK_ID} />, { wrapper: wrapper() })

    expect(await screen.findByText(label('timeEntries.task.forbidden'))).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: label('timeEntries.task.addButton') })).not.toBeInTheDocument()
  })

  it('shows the empty state when there are no entries', async () => {
    fetchTaskTimeEntriesMock.mockResolvedValue(buildResponse({ items: [] }))
    render(<TaskTimeEntriesSection taskId={TASK_ID} />, { wrapper: wrapper() })

    expect(await screen.findByText(label('timeEntries.task.noInterval'))).toBeInTheDocument()
  })
})

describe('TaskTimeEntriesSection — day grouping and collapse (AC-040)', () => {
  it('groups entries under Oggi/Ieri and collapses past 3 with a toggle', async () => {
    const entries = [
      buildEntry({ id: 1, date: getTodayDateKey(), notes: 'Prima di oggi' }),
      buildEntry({ id: 2, date: getTodayDateKey(), notes: 'Seconda di oggi' }),
      buildEntry({ id: 3, date: yesterdayKey(), notes: 'Di ieri' }),
      buildEntry({ id: 4, date: '2026-01-01', notes: 'Vecchia' }),
    ]
    fetchTaskTimeEntriesMock.mockResolvedValue(buildResponse({ items: entries }))
    render(<TaskTimeEntriesSection taskId={TASK_ID} />, { wrapper: wrapper() })

    expect(await screen.findByText(label('timeEntries.task.today'))).toBeInTheDocument()
    expect(screen.getByText(label('timeEntries.task.yesterday'))).toBeInTheDocument()
    expect(screen.getByText('Prima di oggi')).toBeInTheDocument()
    expect(screen.getByText('Seconda di oggi')).toBeInTheDocument()
    expect(screen.getByText('Di ieri')).toBeInTheDocument()
    expect(screen.queryByText('Vecchia')).not.toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: label('timeEntries.task.showAll') }))
    expect(await screen.findByText('Vecchia')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: label('timeEntries.task.showFewer') })).toBeInTheDocument()
  })
})

describe('TaskTimeEntriesSection — create submits the task-scoped payload', () => {
  it('posts without title/links and invalidates the list on success', async () => {
    fetchTaskTimeEntriesMock.mockResolvedValue(buildResponse({ can_create: true, items: [] }))
    createTaskTimeEntryMock.mockResolvedValue(buildEntry({ id: 9 }))
    render(<TaskTimeEntriesSection taskId={TASK_ID} />, { wrapper: wrapper() })

    fireEvent.click(await screen.findByRole('radio', { name: 'Attività' }))
    fireEvent.change(screen.getByLabelText(new RegExp(`^${label('timeEntries.form.minutes')}`)), {
      target: { value: '00:45' },
    })
    fireEvent.click(screen.getByRole('button', { name: label('timeEntries.task.addButton') }))

    await waitFor(() => expect(createTaskTimeEntryMock).toHaveBeenCalled())
    const [calledTaskId, payload] = createTaskTimeEntryMock.mock.calls[0] as [number, Record<string, unknown>]
    expect(calledTaskId).toBe(TASK_ID)
    expect(payload).toMatchObject({ task_type_id: 1, minutes: 45 })
    expect(payload).not.toHaveProperty('title')
    expect(payload).not.toHaveProperty('registry_id')

    // `useCreateTaskTimeEntry` invalidates `timeEntryKeys.all`, so the task's
    // own list refetches — a second call proves the invalidation fired.
    await waitFor(() => expect(fetchTaskTimeEntriesMock).toHaveBeenCalledTimes(2))
  })
})

describe('TaskTimeEntriesSection — row menu and delete', () => {
  it('gates the row menu on the entry\'s own permissions and confirms before deleting', async () => {
    const editableEntry = buildEntry({ id: 1, notes: 'Modificabile', permissions: { update: true, delete: true } })
    const readOnlyEntry = buildEntry({ id: 2, notes: 'Sola lettura', permissions: { update: false, delete: false } })
    fetchTaskTimeEntriesMock.mockResolvedValue(buildResponse({ items: [editableEntry, readOnlyEntry] }))
    deleteTimeEntryMock.mockResolvedValue(undefined)
    render(<TaskTimeEntriesSection taskId={TASK_ID} />, { wrapper: wrapper() })

    await screen.findByText('Modificabile')
    const readOnlyRow = screen.getByText('Sola lettura').closest('div.px-3') as HTMLElement
    expect(within(readOnlyRow).queryByRole('button', { name: label('timeEntries.table.actions') })).not.toBeInTheDocument()

    const editableRow = screen.getByText('Modificabile').closest('div.px-3') as HTMLElement
    // Radix' DropdownMenu trigger opens on `pointerdown`, not `click` (jsdom).
    fireEvent.pointerDown(within(editableRow).getByRole('button', { name: label('timeEntries.table.actions') }), {
      button: 0,
      ctrlKey: false,
    })
    fireEvent.click(await screen.findByRole('menuitem', { name: label('timeEntries.table.delete') }))

    expect(await screen.findByText(label('timeEntries.table.deleteTitle'))).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: label('timeEntries.table.delete') }))

    await waitFor(() => expect(deleteTimeEntryMock).toHaveBeenCalledWith(1))
  })
})
