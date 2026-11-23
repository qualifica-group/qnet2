import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import i18n from '@/i18n'
import FormalabStatusPage from '@/integrations/formalab/components/formalab-status-page'
import { createWrapper } from '@/integrations/formalab/test-support'
import type { FormalabStatus } from '@/integrations/formalab/types'

const fetchStatusMock = vi.fn()
const syncMock = vi.fn()
const testMock = vi.fn()
const activeMock = vi.fn()
const toastSuccess = vi.fn()
const toastError = vi.fn()

vi.mock('@/integrations/formalab/api', () => ({
  FORMALAB_CALL_LOGS_DOMAIN: 'formalab-call-logs',
  fetchFormalabStatus: () => fetchStatusMock(),
  startFormalabSync: () => syncMock(),
  testFormalabConnection: () => testMock(),
}))
vi.mock('@/integrations/use-active-integrations', () => ({ useIsIntegrationActive: () => activeMock() }))
vi.mock('@/routes/breadcrumbs', () => ({ AppBreadcrumbs: () => null }))
vi.mock('@/features/table/table-view', () => ({
  TableView: ({ domain }: { domain: string }) => <div>{`table:${domain}`}</div>,
}))
vi.mock('@/pages/not-found-page', () => ({ default: () => <p>not-found</p> }))
vi.mock('sonner', () => ({
  toast: { success: (m: string) => toastSuccess(m), error: (m: string) => toastError(m) },
}))

const status: FormalabStatus = {
  base_url: 'https://formalab.example.test',
  credentials_configured: true,
  inbound_enabled: false,
  sync_interval_minutes: 10,
  last_sync: {
    id: 1,
    trigger: 'manual',
    outcome: 'failure',
    started_at: '2026-11-20T10:00:00Z',
    finished_at: '2026-11-20T10:00:05Z',
    classes_synced: 0,
    work_orders_updated: 0,
    classes_removed: 2,
    error_message: 'Formalab non raggiungibile',
    warning_message: null,
  },
  classes_count: 1395,
  linked_work_orders_count: 42,
  unlinked_work_orders: { linkable: 1, unmatched: 14, without_course_name: 447 },
  missing_course_fields: [],
  calls_last_24h: { total: 150, failures: 3, inbound: 2, outbound: 148 },
}

describe('FormalabStatusPage', () => {
  beforeEach(async () => {
    await i18n.changeLanguage('it')
    vi.clearAllMocks()
    activeMock.mockReturnValue({ isActive: true, isLoading: false })
    fetchStatusMock.mockResolvedValue(status)
  })

  it('renders nothing of the integration when it is not active', () => {
    activeMock.mockReturnValue({ isActive: false, isLoading: false })
    const { Wrapper } = createWrapper()

    render(<FormalabStatusPage />, { wrapper: Wrapper })

    expect(screen.getByText('not-found')).toBeInTheDocument()
    expect(fetchStatusMock).not.toHaveBeenCalled()
  })

  it('shows the status card and the call log table', async () => {
    const { Wrapper } = createWrapper()

    render(<FormalabStatusPage />, { wrapper: Wrapper })

    expect(await screen.findByText('https://formalab.example.test')).toBeInTheDocument()
    expect(screen.getByText('1395')).toBeInTheDocument()
    expect(screen.getByText('Fallita')).toBeInTheDocument()
    expect(screen.getByText("Classi rimosse (non piu' su Formalab)").nextSibling).toHaveTextContent('2')
    expect(screen.getByRole('alert')).toHaveTextContent('Formalab non raggiungibile')
    expect(screen.getByText('table:formalab-call-logs')).toBeInTheDocument()
  })

  it('tells why the work orders of the linked categories are still unlinked', async () => {
    const { Wrapper } = createWrapper()

    render(<FormalabStatusPage />, { wrapper: Wrapper })

    const card = (await screen.findByRole('heading', { name: 'Commesse non collegate' })).closest('div')
    expect(card).not.toBeNull()
    const rows = within(card as HTMLElement)
    // REQUIREMENT CHANGED (spec 0426 D-7): matching is by edition_id, the "ambiguous" reason is gone
    expect(rows.getByText('Classe scelta non presente nel catalogo').nextSibling).toHaveTextContent('14')
    expect(rows.queryByText('Nome corso di più classi')).not.toBeInTheDocument()
    expect(rows.getByText('Senza classe Formalab scelta').nextSibling).toHaveTextContent('447')
    expect(rows.getByText('Si collegano al prossimo giro').nextSibling).toHaveTextContent('1')
  })

  it('warns about course fields whose attribute code no longer exists', async () => {
    fetchStatusMock.mockResolvedValue({
      ...status,
      missing_course_fields: ['gol_data_esame', 'gol_stato_classe'],
      last_sync: { ...status.last_sync!, outcome: 'success', error_message: null, warning_message: 'Campi corso mancanti' },
    })
    const { Wrapper } = createWrapper()

    render(<FormalabStatusPage />, { wrapper: Wrapper })

    const alert = await screen.findByRole('alert')
    // REQUIREMENT CHANGED (spec 0423 D-8): the check covers the fields mapped in the categories
    expect(alert).toHaveTextContent('Campi mappati non trovati')
    expect(alert).toHaveTextContent('gol_data_esame, gol_stato_classe')
    expect(screen.getByText('Campi corso mancanti')).toBeInTheDocument()
  })

  it('queues a sync and toasts', async () => {
    syncMock.mockResolvedValue(undefined)
    const { Wrapper } = createWrapper()
    render(<FormalabStatusPage />, { wrapper: Wrapper })

    fireEvent.click(await screen.findByRole('button', { name: 'Sincronizza ora' }))

    await waitFor(() => expect(toastSuccess).toHaveBeenCalledWith('Sincronizzazione avviata.'))
  })

  it('reports the connection latency, or the failure', async () => {
    testMock.mockResolvedValueOnce({ ok: true, latency_ms: 120, message: null })
    testMock.mockResolvedValueOnce({ ok: false, latency_ms: null, message: 'Credenziali rifiutate' })
    const { Wrapper } = createWrapper()
    render(<FormalabStatusPage />, { wrapper: Wrapper })

    fireEvent.click(await screen.findByRole('button', { name: 'Verifica connessione' }))
    await waitFor(() => expect(toastSuccess).toHaveBeenCalledWith('Connessione riuscita (120 ms).'))

    fireEvent.click(screen.getByRole('button', { name: 'Verifica connessione' }))
    await waitFor(() => expect(toastError).toHaveBeenCalledWith('Credenziali rifiutate'))
  })
})
