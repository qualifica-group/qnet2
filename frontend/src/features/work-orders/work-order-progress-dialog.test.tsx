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
  { id: 2, percentage: 60, recorded_on: '2026-10-10', created_by: { id: 1, name: 'Mario Rossi' }, created_at: '2026-10-10T08:00:00Z' },
  { id: 1, percentage: 30, recorded_on: '2026-10-01', created_by: null, created_at: '2026-10-01T08:00:00Z' },
]

function detail(overrides: { update?: boolean; forceClosed?: boolean } = {}): WorkOrderDetailWithPermissions {
  return {
    id: 7,
    is_force_closed: overrides.forceClosed ?? false,
    permissions: { resource: { update: overrides.update ?? true }, fields: {}, actions: {} },
  } as unknown as WorkOrderDetailWithPermissions
}

function unprocessable(errors: Record<string, string[]>) {
  return new AxiosError('invalid', '422', undefined, undefined, {
    status: 422,
    data: { message: 'invalid', errors },
  } as AxiosResponse)
}

function renderDialog(onMutated = vi.fn()) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>
        <WorkOrderProgressDialog workOrderId={7} onOpenChange={vi.fn()} onMutated={onMutated} />
      </ConfirmDialogProvider>
    </QueryClientProvider>,
  )
  return onMutated
}

describe('WorkOrderProgressDialog', () => {
  beforeEach(async () => {
    vi.clearAllMocks()
    await i18n.changeLanguage('it')
    fetchEntriesMock.mockResolvedValue(ENTRIES)
    fetchWorkOrderMock.mockResolvedValue(detail())
  })

  it('lists the entries with percentage, date and author', async () => {
    renderDialog()
    const list = await screen.findByRole('list', { name: 'Registrazioni di avanzamento' })
    expect(list).toHaveTextContent('60%')
    expect(list).toHaveTextContent('10/10/2026')
    expect(list).toHaveTextContent('Mario Rossi')
    expect(list).toHaveTextContent('30%')
  })

  it('sends the payload with today as the default date and refreshes the host', async () => {
    createEntryMock.mockResolvedValue(ENTRIES[0])
    const onMutated = renderDialog()
    await screen.findByRole('list')
    const dateInput = await screen.findByLabelText(/Data/)
    expect((dateInput as HTMLInputElement).value).toMatch(/^\d{4}-\d{2}-\d{2}$/)
    fireEvent.change(dateInput, { target: { value: '2026-10-20' } })
    fireEvent.change(screen.getByLabelText(/Percentuale raggiunta/), { target: { value: '70' } })
    fireEvent.click(screen.getByRole('button', { name: 'Registra' }))
    await waitFor(() => expect(createEntryMock).toHaveBeenCalledWith(7, { percentage: 70, recorded_on: '2026-10-20' }))
    await waitFor(() => expect(onMutated).toHaveBeenCalled())
  })

  it('rejects a percentage not above the current one without calling the server', async () => {
    renderDialog()
    await screen.findByRole('list')
    fireEvent.change(await screen.findByLabelText(/Percentuale raggiunta/), { target: { value: '60' } })
    fireEvent.click(screen.getByRole('button', { name: 'Registra' }))
    expect(await screen.findByText('La percentuale deve superare quella attuale (60%).')).toBeInTheDocument()
    expect(createEntryMock).not.toHaveBeenCalled()
  })

  it('shows the server 422 on the field with the accessible error wiring', async () => {
    createEntryMock.mockRejectedValue(unprocessable({ recorded_on: ['Data anteriore all\'ultima registrazione.'] }))
    renderDialog()
    await screen.findByRole('list')
    fireEvent.change(await screen.findByLabelText(/Percentuale raggiunta/), { target: { value: '80' } })
    fireEvent.click(screen.getByRole('button', { name: 'Registra' }))
    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent('Data anteriore all\'ultima registrazione.')
    expect(screen.getByLabelText(/Data/)).toHaveAttribute('aria-invalid', 'true')
  })

  it('deletes an entry after confirmation', async () => {
    deleteEntryMock.mockResolvedValue(undefined)
    const onMutated = renderDialog()
    fireEvent.click(await screen.findByRole('button', { name: 'Elimina la registrazione al 60%' }))
    fireEvent.click(await screen.findByRole('button', { name: 'Elimina' }))
    await waitFor(() => expect(deleteEntryMock).toHaveBeenCalledWith(7, 2))
    await waitFor(() => expect(onMutated).toHaveBeenCalled())
  })

  it.each([
    ['without update permission', detail({ update: false })],
    ['on a force-closed work order', detail({ forceClosed: true })],
  ])('is read-only %s', async (_label, record) => {
    fetchWorkOrderMock.mockResolvedValue(record)
    renderDialog()
    await screen.findByRole('list')
    await waitFor(() => expect(fetchWorkOrderMock).toHaveBeenCalled())
    expect(screen.queryByRole('button', { name: 'Registra' })).not.toBeInTheDocument()
    expect(screen.queryByLabelText(/Percentuale raggiunta/)).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Elimina la registrazione/ })).not.toBeInTheDocument()
  })
})

describe('WorkOrderStatsStrip progress KPI', () => {
  beforeEach(async () => {
    await i18n.changeLanguage('it')
  })

  const base = { startDate: '2026-01-01', callbackDate: null, contractExpiryDate: null }

  it('shows the percentage and the date of the latest entry', () => {
    render(<WorkOrderStatsStrip {...base} completionPercentage={60} completionRecordedOn="2026-10-10" />)
    expect(screen.getByText('60%')).toBeInTheDocument()
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

    expect(await screen.findByRole('dialog', { name: 'Avanzamento commessa' })).toBeInTheDocument()
    await waitFor(() => expect(fetchEntriesMock).toHaveBeenCalledWith(7))
    // Regression: refetching the open record's detail put the record screen
    // into its loading skeleton, which unmounted the popup with it.
    expect(fetchWorkOrderMock).not.toHaveBeenCalled()
  })
})
