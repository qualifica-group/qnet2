import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError, type AxiosResponse } from 'axios'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { WorkOrderProgressDialog } from '@/features/work-orders/work-order-progress-dialog'
import { workOrderDetailQueryKey } from '@/features/work-orders/api'
import { WorkOrderDetailHeader, WorkOrderStatsStrip } from '@/features/work-orders/work-order-detail-header'
import type { WorkOrderDetailWithPermissions, WorkOrderProgressEntry } from '@/features/work-orders/types'

const fetchEntriesMock = vi.fn<(id: number) => Promise<WorkOrderProgressEntry[]>>()
const createEntryMock = vi.fn()
const deleteEntryMock = vi.fn()
vi.mock('@/features/work-orders/progress-api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/work-orders/progress-api')>()),
  fetchProgressEntries: (id: number) => fetchEntriesMock(id),
  createProgressEntry: (...args: unknown[]) => createEntryMock(...args),
  deleteProgressEntry: (...args: unknown[]) => deleteEntryMock(...args),
}))

const fetchWorkOrderMock = vi.fn<(id: number) => Promise<WorkOrderDetailWithPermissions>>()
vi.mock('@/features/work-orders/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/work-orders/api')>()),
  fetchWorkOrder: (id: number) => fetchWorkOrderMock(id),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const ENTRIES: WorkOrderProgressEntry[] = [
  { id: 1, percentage: 30, recorded_on: '2026-10-01', created_by: null, created_at: '2026-10-01T08:00:00Z' },
  { id: 2, percentage: 40, recorded_on: '2026-10-10', created_by: { id: 1, name: 'Mario Rossi' }, created_at: '2026-10-10T08:00:00Z' },
]

function detail(overrides: { update?: boolean } = {}): WorkOrderDetailWithPermissions {
  return {
    id: 7,
    code: 'COM-0007',
    is_force_closed: false,
    permissions: { resource: { update: overrides.update ?? true }, fields: {}, actions: {} },
  } as unknown as WorkOrderDetailWithPermissions
}

function unprocessable(errors: Record<string, string[]>) {
  return new AxiosError('invalid', '422', undefined, undefined, {
    status: 422,
    data: { message: 'invalid', errors },
  } as AxiosResponse)
}

function renderDialog() {
  const onMutated = vi.fn()
  const onOpenChange = vi.fn()
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <WorkOrderProgressDialog workOrderId={7} onOpenChange={onOpenChange} onMutated={onMutated} />
    </QueryClientProvider>,
  )
  return { onMutated, onOpenChange }
}

async function typeStatus(value: string, date = '2026-10-20') {
  fireEvent.change(await screen.findByLabelText(/% Stato attuale/), { target: { value } })
  fireEvent.change(screen.getByLabelText(/^Data/), { target: { value: date } })
}

describe('WorkOrderProgressDialog', () => {
  beforeEach(async () => {
    vi.clearAllMocks()
    await i18n.changeLanguage('it')
    fetchEntriesMock.mockResolvedValue(ENTRIES)
    fetchWorkOrderMock.mockResolvedValue(detail())
  })

  it('starts both the total reached and the current status at 0 when there is no entry', async () => {
    fetchEntriesMock.mockResolvedValue([])
    renderDialog()
    expect(await screen.findByLabelText(/% Stato attuale/)).toHaveValue(0)
    expect(screen.getByLabelText('Totale % raggiunto')).toHaveValue('0')
    expect(screen.getByLabelText(/^Data/)).toHaveValue('')
  })

  it('titles the popup with the code and shows the sum of the increments as the total', async () => {
    renderDialog()
    expect(await screen.findByRole('dialog', { name: 'Lista avanzamenti commessa n. COM-0007' })).toBeInTheDocument()
    await waitFor(() => expect(screen.getByLabelText('Totale % raggiunto')).toHaveValue('70'))
    expect(screen.getByLabelText('Totale % raggiunto')).toHaveAttribute('readonly')
  })

  it('lists the rows with date and increment, and starts with an empty date', async () => {
    renderDialog()
    const rows = await screen.findAllByRole('row')
    expect(rows[1]).toHaveTextContent('01/10/2026')
    expect(rows[1]).toHaveTextContent('30')
    expect(rows[2]).toHaveTextContent('10/10/2026')
    expect(rows[2]).toHaveTextContent('40')
    expect(screen.getByLabelText(/^Data/)).toHaveValue('')
  })

  it('sends the typed total with the date, refreshes the host and closes on Conferma', async () => {
    createEntryMock.mockResolvedValue(ENTRIES[0])
    const { onMutated, onOpenChange } = renderDialog()
    await waitFor(() => expect(screen.getByLabelText('Totale % raggiunto')).toHaveValue('70'))
    await typeStatus('90')
    fireEvent.click(screen.getByRole('button', { name: 'Conferma' }))
    await waitFor(() => expect(createEntryMock).toHaveBeenCalledWith(7, { percentage: 90, recorded_on: '2026-10-20' }))
    await waitFor(() => expect(onMutated).toHaveBeenCalled())
    await waitFor(() => expect(onOpenChange).toHaveBeenCalledWith(false))
  })

  it('rejects a total not above the reached one, and a missing date, without calling the server', async () => {
    renderDialog()
    await waitFor(() => expect(screen.getByLabelText('Totale % raggiunto')).toHaveValue('70'))
    fireEvent.change(await screen.findByLabelText(/% Stato attuale/), { target: { value: '70' } })
    fireEvent.click(screen.getByRole('button', { name: 'Conferma' }))
    expect(await screen.findByText('Il totale deve essere maggiore dello stato raggiunto.')).toBeInTheDocument()
    expect(await screen.findByText('Indica la data.')).toBeInTheDocument()
    expect(screen.getByLabelText(/% Stato attuale/)).toHaveAttribute('aria-invalid', 'true')
    expect(createEntryMock).not.toHaveBeenCalled()
  })

  it('shows the server 422 on the field and keeps the popup open', async () => {
    createEntryMock.mockRejectedValue(unprocessable({ percentage: ['Il totale deve essere maggiore dello stato raggiunto.'] }))
    const { onOpenChange } = renderDialog()
    await waitFor(() => expect(screen.getByLabelText('Totale % raggiunto')).toHaveValue('70'))
    await typeStatus('80')
    fireEvent.click(screen.getByRole('button', { name: 'Conferma' }))
    expect(await screen.findByRole('alert')).toHaveTextContent('Il totale deve essere maggiore dello stato raggiunto.')
    expect(screen.getByLabelText(/% Stato attuale/)).toHaveAttribute('aria-invalid', 'true')
    expect(onOpenChange).not.toHaveBeenCalled()
  })

  it('deletes a row with an inline confirm, no extra dialog, and the popup stays open', async () => {
    deleteEntryMock.mockResolvedValue(undefined)
    const { onMutated, onOpenChange } = renderDialog()
    fireEvent.click(await screen.findByRole('button', { name: 'Elimina la registrazione del 10/10/2026' }))
    expect(deleteEntryMock).not.toHaveBeenCalled()
    expect(screen.getByRole('button', { name: "Annulla l'eliminazione" })).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: "Conferma l'eliminazione della registrazione del 10/10/2026" }))
    await waitFor(() => expect(deleteEntryMock).toHaveBeenCalledWith(7, 2))
    await waitFor(() => expect(onMutated).toHaveBeenCalled())
    expect(onOpenChange).not.toHaveBeenCalled()
  })

  it('cancels the inline delete without calling the server', async () => {
    renderDialog()
    fireEvent.click(await screen.findByRole('button', { name: 'Elimina la registrazione del 10/10/2026' }))
    fireEvent.click(screen.getByRole('button', { name: "Annulla l'eliminazione" }))
    expect(screen.getByRole('button', { name: 'Elimina la registrazione del 10/10/2026' })).toBeInTheDocument()
    expect(deleteEntryMock).not.toHaveBeenCalled()
  })

  it('is read-only without the update permission: list and total only', async () => {
    fetchWorkOrderMock.mockResolvedValue(detail({ update: false }))
    renderDialog()
    await waitFor(() => expect(screen.getByLabelText('Totale % raggiunto')).toHaveValue('70'))
    await waitFor(() => expect(fetchWorkOrderMock).toHaveBeenCalled())
    expect(screen.getAllByRole('row')).toHaveLength(3)
    expect(screen.queryByRole('button', { name: 'Conferma' })).not.toBeInTheDocument()
    expect(screen.queryByLabelText(/% Stato attuale/)).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Elimina la registrazione/ })).not.toBeInTheDocument()
  })
})

describe('WorkOrderStatsStrip progress KPI', () => {
  beforeEach(async () => {
    await i18n.changeLanguage('it')
  })

  const base = { startDate: '2026-01-01', callbackDate: null, contractExpiryDate: null }

  it('shows the total as text with the date of the latest entry', () => {
    render(<WorkOrderStatsStrip {...base} completionPercentage={130} completionRecordedOn="2026-10-10" />)
    expect(screen.getByText('130%')).toBeInTheDocument()
    expect(screen.getByText('10/10/2026')).toBeInTheDocument()
  })

  it('shows a dash instead of the date when there is no entry', () => {
    render(<WorkOrderStatsStrip {...base} completionPercentage={0} completionRecordedOn={null} />)
    expect(screen.getByText('—', { selector: 'span.truncate' })).toBeInTheDocument()
  })
})

describe('WorkOrderDetailHeader progress action', () => {
  beforeEach(async () => {
    vi.clearAllMocks()
    await i18n.changeLanguage('it')
    fetchEntriesMock.mockResolvedValue(ENTRIES)
    fetchWorkOrderMock.mockResolvedValue(detail())
  })

  it('opens the progress popup from the header action, like the grid row action', async () => {
    const workOrder = {
      ...detail(),
      title: 'Commessa',
      code: 'COM-1',
      old_id: null,
      type: 'processing',
      is_multi_quote: false,
      status: { value: 'in_progress', is_force_closed: false },
    } as unknown as WorkOrderDetailWithPermissions
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    render(
      <QueryClientProvider client={client}>
        <ConfirmDialogProvider>
          <WorkOrderDetailHeader workOrder={workOrder} />
        </ConfirmDialogProvider>
      </QueryClientProvider>,
    )

    client.setQueryData(workOrderDetailQueryKey(7), workOrder)
    fireEvent.click(screen.getByRole('button', { name: 'Avanzamento commessa' }))

    expect(await screen.findByRole('dialog', { name: 'Lista avanzamenti commessa n. COM-1' })).toBeInTheDocument()
    await waitFor(() => expect(fetchEntriesMock).toHaveBeenCalledWith(7))
    // Regression: refetching the open record's detail put the record screen
    // into its loading skeleton, which unmounted the popup with it.
    expect(fetchWorkOrderMock).not.toHaveBeenCalled()
  })
})
