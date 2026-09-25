import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ColDef, ICellRendererParams } from 'ag-grid-community'
import i18n from '@/i18n'
import { TooltipProvider } from '@/components/ui/tooltip'
import { TimeEntryDayEntriesTable } from '@/features/time-entries/days/time-entry-day-entries-table'
import type { TimeEntry } from '@/features/time-entries/types'

// Enterprise bootstrap is a module-load side effect irrelevant to this suite.
vi.mock('@/components/data-table/ag-grid-setup', () => ({ setupAgGrid: () => {} }))

vi.mock('@/features/appearance/ui-scale-context', () => ({ useUiScale: () => ({ factor: 1 }) }))

vi.mock('@/features/time-entries/api', () => ({
  updateTimeEntry: vi.fn(),
  deleteTimeEntry: vi.fn(),
}))

vi.mock('@/features/task-types/for-select-api', () => ({
  fetchTaskTypesForSelect: () =>
    Promise.resolve({
      items: [
        { id: 3, label: 'Chiamata', meta: { color: 'blue', icon: 'phone' } },
        { id: 4, label: 'Ticket', meta: { color: 'red', icon: null } },
      ],
      export_link: null,
      pagination: { total: 2, offset: 0, limit: 100, total_pages: 1 },
    }),
}))

// AG Grid stands in as a plain semantic table that still invokes the real
// `cellRenderer` of every colDef (same convention as
// `opportunity-quotes-detail-renderer.test.tsx`) — so AC-036's gating is
// asserted against the actual title/actions cell renderers, not a stub.
vi.mock('ag-grid-react', () => ({
  AgGridReact: ({ columnDefs, rowData }: { columnDefs: ColDef<TimeEntry>[]; rowData: TimeEntry[] }) => (
    <table>
      <tbody>
        {rowData.map((row) => (
          <tr key={row.id}>
            {columnDefs.map((def) => (
              <td key={def.colId}>
                {def.cellRenderer
                  ? (def.cellRenderer as (params: ICellRendererParams<TimeEntry>) => React.ReactNode)({
                      data: row,
                    } as ICellRendererParams<TimeEntry>)
                  : null}
              </td>
            ))}
          </tr>
        ))}
      </tbody>
    </table>
  ),
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <TooltipProvider>{children}</TooltipProvider>
    </QueryClientProvider>
  )
}

function entry(overrides: Partial<TimeEntry> = {}): TimeEntry {
  return {
    id: 1,
    user: { id: 1, name: 'Mario' },
    date: '2026-09-14',
    title: 'Chiamata cliente',
    task_type: { id: 3, name: 'Chiamata', color: 'blue', icon: 'phone' },
    start_time: '09:00',
    end_time: '09:30',
    minutes: 30,
    notes: null,
    registry: null,
    opportunity: null,
    work_order: null,
    task: null,
    work_order_stage: null,
    created_at: '',
    updated_at: '',
    permissions: { update: true, delete: true },
    ...overrides,
  }
}

/** Opens a Radix `DropdownMenu`: its trigger opens on `pointerdown`, not `click`. */
function openMenu(trigger: HTMLElement) {
  fireEvent.pointerDown(trigger, { button: 0, ctrlKey: false })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.clearAllMocks()
})

describe('TimeEntryDayEntriesTable row gating (AC-036)', () => {
  it('hides the row actions menu and disables the type-change button when canWrite is false', () => {
    const Wrapper = wrapper()
    render(<TimeEntryDayEntriesTable canWrite={false} entries={[entry()]} onEditEntry={vi.fn()} />, {
      wrapper: Wrapper,
    })

    expect(screen.queryByRole('button', { name: 'Actions' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Change type' })).toBeDisabled()
    // The title itself is not openable either (`canOpenEntry` mirrors `canWrite`).
    expect(screen.getByRole('button', { name: 'Chiamata cliente' })).toBeDisabled()
  })

  it("hides the row actions menu when the entry's own permissions deny update/delete, even with canWrite true", () => {
    const Wrapper = wrapper()
    render(
      <TimeEntryDayEntriesTable
        canWrite
        entries={[entry({ permissions: { update: false, delete: false } })]}
        onEditEntry={vi.fn()}
      />,
      { wrapper: Wrapper },
    )

    expect(screen.queryByRole('button', { name: 'Actions' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Change type' })).toBeDisabled()
    expect(screen.getByRole('button', { name: 'Chiamata cliente' })).toBeDisabled()
  })

  it('shows a working row actions menu (Edit/Delete) when canWrite is true and the entry allows it', () => {
    const onEditEntry = vi.fn()
    const Wrapper = wrapper()
    render(<TimeEntryDayEntriesTable canWrite entries={[entry()]} onEditEntry={onEditEntry} />, {
      wrapper: Wrapper,
    })

    const actionsTrigger = screen.getByRole('button', { name: 'Actions' })
    openMenu(actionsTrigger)
    const menu = screen.getByRole('menu')
    expect(within(menu).getByText('Edit')).toBeInTheDocument()
    expect(within(menu).getByText('Delete')).toBeInTheDocument()

    fireEvent.click(within(menu).getByText('Edit'))
    expect(onEditEntry).toHaveBeenCalledWith(1)
  })

  it('enables the type-change dropdown, listing the task type options, when canWrite is true', async () => {
    const Wrapper = wrapper()
    render(<TimeEntryDayEntriesTable canWrite entries={[entry()]} onEditEntry={vi.fn()} />, {
      wrapper: Wrapper,
    })

    // Options load asynchronously (`useTimeEntryTaskTypeOptions`); the button
    // starts disabled and enables once the fetch resolves.
    await waitFor(() => expect(screen.getByRole('button', { name: 'Change type' })).not.toBeDisabled())
    const typeTrigger = screen.getByRole('button', { name: 'Change type' })

    openMenu(typeTrigger)
    const menu = screen.getByRole('menu')
    expect(within(menu).getByText('Chiamata')).toBeInTheDocument()
    expect(within(menu).getByText('Ticket')).toBeInTheDocument()
  })
})
