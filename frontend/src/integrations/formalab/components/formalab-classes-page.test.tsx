import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import FormalabClassesPage from '@/integrations/formalab/components/formalab-classes-page'
import { createWrapper } from '@/integrations/formalab/test-support'

const syncMock = vi.fn()
const fetchSyncStateMock = vi.fn()
const activeMock = vi.fn()
const toastSuccess = vi.fn()

vi.mock('@/integrations/formalab/api', () => ({
  FORMALAB_CLASSES_DOMAIN: 'formalab-classes',
  startFormalabSync: () => syncMock(),
  fetchFormalabSyncState: () => fetchSyncStateMock(),
  testFormalabConnection: vi.fn(),
}))
vi.mock('@/integrations/use-active-integrations', () => ({ useIsIntegrationActive: () => activeMock() }))
vi.mock('@/routes/breadcrumbs', () => ({ AppBreadcrumbs: () => null }))
vi.mock('@/features/table/table-view', () => ({
  TableView: ({ domain }: { domain: string }) => <div>{`table:${domain}`}</div>,
}))
vi.mock('@/pages/not-found-page', () => ({ default: () => <p>not-found</p> }))
vi.mock('sonner', () => ({ toast: { success: (m: string) => toastSuccess(m), error: vi.fn() } }))

describe('FormalabClassesPage', () => {
  beforeEach(async () => {
    await i18n.changeLanguage('it')
    vi.clearAllMocks()
    activeMock.mockReturnValue({ isActive: true, isLoading: false })
    fetchSyncStateMock.mockResolvedValue({ sync_in_progress: false, last_sync: null })
  })

  // User request 2026-11-23: never two syncs together, from either page
  it('disables the sync while one is running', async () => {
    fetchSyncStateMock.mockResolvedValue({ sync_in_progress: true, last_sync: null })
    const { Wrapper } = createWrapper()

    render(<FormalabClassesPage />, { wrapper: Wrapper })

    expect(await screen.findByRole('button', { name: 'Sincronizzazione in corso' })).toBeDisabled()
  })

  it('is not found when the integration is not active', () => {
    activeMock.mockReturnValue({ isActive: false, isLoading: false })
    const { Wrapper } = createWrapper()

    render(<FormalabClassesPage />, { wrapper: Wrapper })

    expect(screen.getByText('not-found')).toBeInTheDocument()
  })

  it('shows the classes table and queues a sync on click', async () => {
    syncMock.mockResolvedValue(undefined)
    const { Wrapper } = createWrapper()

    render(<FormalabClassesPage />, { wrapper: Wrapper })

    expect(screen.getByText('table:formalab-classes')).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Sincronizza ora' }))

    await waitFor(() => expect(syncMock).toHaveBeenCalledTimes(1))
    await waitFor(() => expect(toastSuccess).toHaveBeenCalledWith('Sincronizzazione avviata.'))
  })
})
