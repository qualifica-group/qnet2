import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { workOrderDetailQueryKey } from '@/features/work-orders/api'
import { FormalabWorkOrderPanel } from '@/integrations/formalab/components/work-order-panel'
import { buildLink, buildWorkOrderLink, createWrapper } from '@/integrations/formalab/test-support'

const fetchLinkMock = vi.fn()
const syncLinkMock = vi.fn()
const toastSuccess = vi.fn()
const toastError = vi.fn()

vi.mock('@/integrations/formalab/api', () => ({
  fetchFormalabWorkOrderLink: (id: number) => fetchLinkMock(id),
  syncFormalabWorkOrderLink: (id: number) => syncLinkMock(id),
}))
vi.mock('sonner', () => ({
  toast: { success: (m: string) => toastSuccess(m), error: (m: string) => toastError(m) },
}))

describe('FormalabWorkOrderPanel', () => {
  beforeEach(async () => {
    await i18n.changeLanguage('it')
    vi.clearAllMocks()
  })

  it('shows the class data of a synced work order', async () => {
    fetchLinkMock.mockResolvedValue(buildWorkOrderLink({ link: buildLink({ sync_error: 'Timeout' }) }))
    const { Wrapper } = createWrapper()

    render(<FormalabWorkOrderPanel workOrderId={7} />, { wrapper: Wrapper })

    expect(await screen.findByText('ED101 - Operatore di magazzino')).toBeInTheDocument()
    expect(screen.getByText('Sede Roma - Roma')).toBeInTheDocument()
    expect(screen.getByRole('alert')).toHaveTextContent('Timeout')
  })

  it('offers only Sincronizza, never an edition choice, and refreshes the work order detail', async () => {
    fetchLinkMock.mockResolvedValue(buildWorkOrderLink({ link: null }))
    syncLinkMock.mockResolvedValue(buildWorkOrderLink({ link: buildLink() }))
    const { Wrapper, client } = createWrapper()
    const invalidate = vi.spyOn(client, 'invalidateQueries')

    render(<FormalabWorkOrderPanel workOrderId={7} />, { wrapper: Wrapper })

    // REQUIREMENT CHANGED (spec 0426): the not-linked hint now points to the class select
    expect(await screen.findByText(/Nessuna classe collegata/)).toBeInTheDocument()
    expect(screen.queryByRole('combobox')).not.toBeInTheDocument()
    expect(screen.getAllByRole('button')).toHaveLength(1)

    fireEvent.click(screen.getByRole('button', { name: 'Sincronizza' }))

    await waitFor(() => expect(syncLinkMock).toHaveBeenCalledWith(7))
    await waitFor(() => expect(invalidate).toHaveBeenCalledWith({ queryKey: workOrderDetailQueryKey(7) }))
    expect(await screen.findByText('ED101 - Operatore di magazzino')).toBeInTheDocument()
    expect(toastSuccess).toHaveBeenCalledWith('Dati aggiornati da Formalab.')
  })

  it('shows the server reason when the course name matches no edition', async () => {
    fetchLinkMock.mockResolvedValue(buildWorkOrderLink({ link: null }))
    syncLinkMock.mockRejectedValue({
      isAxiosError: true,
      response: { status: 422, data: { success: false, message: 'Nessuna edizione Formalab ha questo Nome Corso / Ed.' } },
    })
    const { Wrapper } = createWrapper()

    render(<FormalabWorkOrderPanel workOrderId={7} />, { wrapper: Wrapper })
    fireEvent.click(await screen.findByRole('button', { name: 'Sincronizza' }))

    await waitFor(() => expect(toastError).toHaveBeenCalled())
  })

  it('offers no action without the manage permission', async () => {
    fetchLinkMock.mockResolvedValue(buildWorkOrderLink({ can_manage: false, link: buildLink() }))
    const { Wrapper } = createWrapper()

    render(<FormalabWorkOrderPanel workOrderId={7} />, { wrapper: Wrapper })

    await screen.findByText('ED101 - Operatore di magazzino')
    expect(screen.queryByRole('button', { name: 'Sincronizza' })).not.toBeInTheDocument()
  })
})
