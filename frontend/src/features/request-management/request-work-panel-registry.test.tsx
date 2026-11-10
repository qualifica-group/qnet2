import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { RequestWorkPanelScreen } from '@/features/request-management/request-work-panel'
import { workPanel as panel } from '@/features/request-management/request-work-panel-fixtures'

/** The client select of the work panel (decisione utente 2026-11-09, spec 0371). */

const fetchRequestWorkPanelMock = vi.fn()
const updateRequestWorkMock = vi.fn()
vi.mock('@/features/request-management/api', () => ({
  fetchRequestWorkPanel: (...args: unknown[]) => fetchRequestWorkPanelMock(...args),
  updateRequestWork: (...args: unknown[]) => updateRequestWorkMock(...args),
}))

vi.mock('@/features/personal-data/api', () => ({
  createContact: vi.fn(),
  updateContact: vi.fn(),
  deleteContact: vi.fn(),
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('@/features/product-categories/use-product-category-tree', () => ({
  useProductCategoryTree: () => ({ data: [], isPending: false, isError: false, refetch: vi.fn() }),
}))

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: () => <div>activity-log</div>,
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', () => ({
  FOR_SELECT_PAGE_SIZE: 25,
  fetchForSelect: (...args: unknown[]) => fetchForSelectMock(...args),
}))

const OTHER_CLIENT = { id: 77, label: 'Beta S.r.l.', subtitle: null }

function renderPanel() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>
        <RequestWorkPanelScreen id={4001} />
      </ConfirmDialogProvider>
    </QueryClientProvider>,
  )
}

async function pickOtherClient() {
  fireEvent.click(await screen.findByRole('combobox', { name: 'Anagrafica' }))
  fireEvent.click(await screen.findByRole('option', { name: /Beta S\.r\.l\./ }))
}

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  fetchRequestWorkPanelMock.mockReset()
  updateRequestWorkMock.mockReset()
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockImplementation((resource: string) => {
    const items = resource === 'registries' ? [OTHER_CLIENT] : []
    return Promise.resolve({ items, pagination: { total: items.length, offset: 0, limit: 25, total_pages: 1, has_more: false }, export_link: null })
  })
})

describe('work panel: client select', () => {
  it('shows the current client above the client data', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())

    renderPanel()

    expect(await screen.findByRole('combobox', { name: 'Anagrafica' })).toHaveTextContent('Acme S.p.A.')
    expect(screen.getByRole('heading', { name: 'Contatti' })).toBeInTheDocument()
  })

  it('hides the client data while another client is picked, and saves only the new client', async () => {
    const loaded = panel()
    fetchRequestWorkPanelMock.mockResolvedValue(loaded)
    updateRequestWorkMock.mockResolvedValue(loaded)

    renderPanel()
    await pickOtherClient()

    await waitFor(() => expect(screen.queryByRole('heading', { name: 'Contatti' })).not.toBeInTheDocument())
    expect(screen.getByText(/dopo il salvataggio/)).toBeInTheDocument()

    fireEvent.submit(document.getElementById('request-work-form') as HTMLFormElement)

    await waitFor(() => expect(updateRequestWorkMock).toHaveBeenCalled())
    expect(updateRequestWorkMock.mock.calls[0][2]).toEqual({ registry_id: 77 })
  })

  it.each([
    ['contract' as const, /esiste già un contratto/],
    ['lead' as const, /arriva dal lead/],
  ])('is read-only and says why when locked by %s', async (reason, message) => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel({ registry_lock_reason: reason }))

    renderPanel()

    expect(await screen.findByRole('combobox', { name: 'Anagrafica' })).toBeDisabled()
    expect(screen.getByText(message)).toBeInTheDocument()
  })
})
