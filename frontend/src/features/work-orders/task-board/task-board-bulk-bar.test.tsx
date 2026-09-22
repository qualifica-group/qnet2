import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { toast } from 'sonner'
import '@/i18n'
import { TaskBoardBulkBar } from '@/features/work-orders/task-board/task-board-bulk-bar'

const bulkBoardTaskActionMock = vi.fn()

vi.mock('@/features/work-orders/task-board/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/work-orders/task-board/api')>(
    '@/features/work-orders/task-board/api',
  )
  return { ...actual, bulkBoardTaskAction: (...args: unknown[]) => bulkBoardTaskActionMock(...args) }
})

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

// AC-017/AC-028 exercise the DIALOG'S own submit wiring, not the async picker
// network path (already covered by `async-paginated-*.test.tsx`), mirroring
// `task-complete-dialog.test.tsx`'s own stubs.
vi.mock('@/components/ui/async-paginated-multi-select', () => ({
  AsyncPaginatedMultiSelect: ({ labels, onChange }: { labels: { triggerLabel: string }; onChange: (value: number[]) => void }) => (
    <button type="button" onClick={() => onChange([31])}>
      {labels.triggerLabel}
    </button>
  ),
}))
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({ labels, onChange }: { labels: { triggerLabel: string }; onChange: (value: number | null) => void }) => (
    <button type="button" onClick={() => onChange(7)}>
      {labels.triggerLabel}
    </button>
  ),
}))
vi.mock('@/features/time-entries/form/time-entry-type-picker', () => ({
  useTimeEntryTypeOptions: () => ({
    options: [{ id: 2, label: 'Attività', meta: { color: 'blue', icon: 'clipboard-list' } }],
    isPending: false,
    isError: false,
    refetch: vi.fn(),
  }),
  TimeEntryTypePicker: ({ value }: { value: number | null }) => <div>{value ?? ''}</div>,
}))

function renderBulkBar(selectedTaskIds: number[], onClear = vi.fn()) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <TaskBoardBulkBar workOrderId={9} selectedTaskIds={selectedTaskIds} onClear={onClear} />
    </QueryClientProvider>,
  )
  return { onClear }
}

beforeEach(() => {
  bulkBoardTaskActionMock.mockReset()
  vi.mocked(toast.success).mockReset()
  vi.mocked(toast.error).mockReset()
})

describe('TaskBoardBulkBar', () => {
  it('is hidden (aria-hidden, no pointer events) with an empty selection', () => {
    renderBulkBar([])

    const bar = screen.getByText('0 task selezionati').closest('[aria-hidden]')
    expect(bar).toHaveAttribute('aria-hidden', 'true')
  })

  it('shows the selected count and is interactive once a task is selected', () => {
    renderBulkBar([1, 2, 3])

    const bar = screen.getByText('3 task selezionati').closest('[aria-hidden]')
    expect(bar).toHaveAttribute('aria-hidden', 'false')
  })

  it('Assegna: submits assignee_ids and clears the selection on success', async () => {
    bulkBoardTaskActionMock.mockResolvedValue({ results: [], succeeded: 2, failed: 0 })
    const { onClear } = renderBulkBar([1, 2])

    fireEvent.click(screen.getByRole('button', { name: /Assegna/ }))
    fireEvent.click(await screen.findByRole('button', { name: 'Assegnatari' }))
    fireEvent.click(screen.getByRole('button', { name: 'Assegna' }))

    await waitFor(() =>
      expect(bulkBoardTaskActionMock).toHaveBeenCalledWith(9, { action: 'assign', task_ids: [1, 2], assignee_ids: [31] }),
    )
    await waitFor(() => expect(toast.success).toHaveBeenCalled())
    expect(onClear).toHaveBeenCalled()
  })

  it('Completa: submits closure_feedback and time_entry together', async () => {
    bulkBoardTaskActionMock.mockResolvedValue({ results: [], succeeded: 1, failed: 0 })
    const { onClear } = renderBulkBar([5])

    fireEvent.click(screen.getByRole('button', { name: /Completa/ }))
    fireEvent.change(await screen.findByLabelText('Feedback di chiusura'), {
      target: { value: 'Consegnato' },
    })
    fireEvent.change(screen.getByLabelText(/Tempo/), { target: { value: '01:00' } })
    fireEvent.click(screen.getByRole('button', { name: 'Completa', hidden: false }))

    await waitFor(() =>
      expect(bulkBoardTaskActionMock).toHaveBeenCalledWith(9, {
        action: 'complete',
        task_ids: [5],
        closure_feedback: 'Consegnato',
        time_entry: expect.objectContaining({ minutes: 60 }),
      }),
    )
    expect(onClear).toHaveBeenCalled()
  })

  it('Riapri: sends only task_ids, no body', async () => {
    bulkBoardTaskActionMock.mockResolvedValue({ results: [], succeeded: 1, failed: 0 })
    renderBulkBar([8])

    fireEvent.click(screen.getByRole('button', { name: /Riapri/ }))
    fireEvent.click(await screen.findByRole('button', { name: 'Riapri' }))

    await waitFor(() => expect(bulkBoardTaskActionMock).toHaveBeenCalledWith(9, { action: 'uncomplete', task_ids: [8] }))
  })

  it('Blocca: sends only task_ids, no body', async () => {
    bulkBoardTaskActionMock.mockResolvedValue({ results: [], succeeded: 1, failed: 0 })
    renderBulkBar([8])

    fireEvent.click(screen.getByRole('button', { name: /Blocca/ }))
    fireEvent.click(await screen.findByRole('button', { name: 'Blocca' }))

    await waitFor(() => expect(bulkBoardTaskActionMock).toHaveBeenCalledWith(9, { action: 'block', task_ids: [8] }))
  })

  it('Priorità: submits task_priority_id', async () => {
    bulkBoardTaskActionMock.mockResolvedValue({ results: [], succeeded: 1, failed: 0 })
    renderBulkBar([4])

    fireEvent.click(screen.getByRole('button', { name: /Priorità/ }))
    fireEvent.click(await screen.findByRole('button', { name: 'Priorità' }))
    fireEvent.click(screen.getByRole('button', { name: 'Applica' }))

    await waitFor(() =>
      expect(bulkBoardTaskActionMock).toHaveBeenCalledWith(9, { action: 'priority', task_ids: [4], task_priority_id: 7 }),
    )
  })

  it('Date: requires at least one date before submitting', async () => {
    renderBulkBar([4])

    fireEvent.click(screen.getByRole('button', { name: /Date/ }))
    fireEvent.click(await screen.findByRole('button', { name: 'Applica' }))

    expect(await screen.findByText('Imposta almeno una data.')).toBeInTheDocument()
    expect(bulkBoardTaskActionMock).not.toHaveBeenCalled()
  })

  it('Date: submits the filled dates', async () => {
    bulkBoardTaskActionMock.mockResolvedValue({ results: [], succeeded: 1, failed: 0 })
    renderBulkBar([4])

    fireEvent.click(screen.getByRole('button', { name: /Date/ }))
    fireEvent.change(await screen.findByLabelText('Data inizio'), { target: { value: '2026-10-01' } })
    fireEvent.click(screen.getByRole('button', { name: 'Applica' }))

    await waitFor(() =>
      expect(bulkBoardTaskActionMock).toHaveBeenCalledWith(9, {
        action: 'dates',
        task_ids: [4],
        start_date: '2026-10-01',
        end_date: null,
      }),
    )
  })

  it('shows a failure summary with reasons when some tasks fail (AC-028)', async () => {
    bulkBoardTaskActionMock.mockResolvedValue({
      results: [{ task_id: 8, ok: false, message: 'Non autorizzato' }],
      succeeded: 0,
      failed: 1,
    })
    renderBulkBar([8])

    fireEvent.click(screen.getByRole('button', { name: /Blocca/ }))
    fireEvent.click(await screen.findByRole('button', { name: 'Blocca' }))

    await waitFor(() => expect(toast.error).toHaveBeenCalled())
    expect(toast.success).not.toHaveBeenCalled()
  })
})
