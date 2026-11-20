import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { workOrderDetailQueryKey } from '@/features/work-orders/api'
import { FormalabWorkOrderPanel } from '@/integrations/formalab/components/work-order-panel'
import { buildClass, buildLink, buildWorkOrderLink, createWrapper } from '@/integrations/formalab/test-support'

const fetchLinkMock = vi.fn()
const fetchClassesMock = vi.fn()
const setLinkMock = vi.fn()
const removeLinkMock = vi.fn()
const syncLinkMock = vi.fn()
const confirmMock = vi.fn()

vi.mock('@/integrations/formalab/api', () => ({
  fetchFormalabWorkOrderLink: (id: number) => fetchLinkMock(id),
  fetchFormalabClasses: (search: string) => fetchClassesMock(search),
  setFormalabWorkOrderLink: (id: number, edition: number) => setLinkMock(id, edition),
  removeFormalabWorkOrderLink: (id: number) => removeLinkMock(id),
  syncFormalabWorkOrderLink: (id: number) => syncLinkMock(id),
}))
vi.mock('@/components/confirm-dialog-context', () => ({ useConfirm: () => confirmMock }))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

describe('FormalabWorkOrderPanel', () => {
  beforeEach(async () => {
    await i18n.changeLanguage('it')
    vi.clearAllMocks()
    fetchClassesMock.mockResolvedValue([buildClass()])
  })

  it('shows the class data of a linked work order', async () => {
    fetchLinkMock.mockResolvedValue(buildWorkOrderLink({ link: buildLink({ sync_error: 'Timeout' }) }))
    const { Wrapper } = createWrapper()

    render(<FormalabWorkOrderPanel workOrderId={7} />, { wrapper: Wrapper })

    expect(await screen.findByText('ED101 - Operatore di magazzino')).toBeInTheDocument()
    expect(screen.getByText('Sede Roma - Roma')).toBeInTheDocument()
    expect(screen.getByRole('alert')).toHaveTextContent('Timeout')
  })

  it('links an edition picked from the catalogue and refreshes the work order detail', async () => {
    fetchLinkMock.mockResolvedValue(buildWorkOrderLink({ link: null }))
    setLinkMock.mockResolvedValue(buildWorkOrderLink({ link: buildLink() }))
    const { Wrapper, client } = createWrapper()
    const invalidate = vi.spyOn(client, 'invalidateQueries')

    render(<FormalabWorkOrderPanel workOrderId={7} />, { wrapper: Wrapper })

    fireEvent.click(await screen.findByRole('combobox', { name: 'Edizione Formalab' }))
    fireEvent.click(await screen.findByRole('option', { name: 'ED101 - Operatore di magazzino' }))
    fireEvent.click(screen.getByRole('button', { name: 'Collega' }))

    await waitFor(() => expect(setLinkMock).toHaveBeenCalledWith(7, 101))
    await waitFor(() =>
      expect(invalidate).toHaveBeenCalledWith({ queryKey: workOrderDetailQueryKey(7) }),
    )
    expect(await screen.findByRole('button', { name: 'Scollega' })).toBeInTheDocument()
  })

  it('re-reads the linked edition with Sincronizza and refreshes the work order detail', async () => {
    fetchLinkMock.mockResolvedValue(buildWorkOrderLink({ link: buildLink() }))
    syncLinkMock.mockResolvedValue(buildWorkOrderLink({ link: buildLink() }))
    const { Wrapper, client } = createWrapper()
    const invalidate = vi.spyOn(client, 'invalidateQueries')

    render(<FormalabWorkOrderPanel workOrderId={7} />, { wrapper: Wrapper })

    fireEvent.click(await screen.findByRole('button', { name: 'Sincronizza' }))

    await waitFor(() => expect(syncLinkMock).toHaveBeenCalledWith(7))
    await waitFor(() => expect(invalidate).toHaveBeenCalledWith({ queryKey: workOrderDetailQueryKey(7) }))
    expect(fetchClassesMock).not.toHaveBeenCalled()
  })

  it('unlinks only after the confirmation', async () => {
    fetchLinkMock.mockResolvedValue(buildWorkOrderLink({ link: buildLink() }))
    removeLinkMock.mockResolvedValue(buildWorkOrderLink({ link: null }))
    confirmMock.mockResolvedValueOnce(false).mockResolvedValueOnce(true)
    const { Wrapper } = createWrapper()

    render(<FormalabWorkOrderPanel workOrderId={7} />, { wrapper: Wrapper })

    fireEvent.click(await screen.findByRole('button', { name: 'Scollega' }))
    await waitFor(() => expect(confirmMock).toHaveBeenCalledTimes(1))
    expect(removeLinkMock).not.toHaveBeenCalled()

    fireEvent.click(screen.getByRole('button', { name: 'Scollega' }))
    await waitFor(() => expect(removeLinkMock).toHaveBeenCalledWith(7))
    expect(await screen.findByRole('button', { name: 'Collega' })).toBeInTheDocument()
  })

  it('offers no actions without the manage permission', async () => {
    fetchLinkMock.mockResolvedValue(buildWorkOrderLink({ can_manage: false, link: buildLink() }))
    const { Wrapper } = createWrapper()

    render(<FormalabWorkOrderPanel workOrderId={7} />, { wrapper: Wrapper })

    await screen.findByText('ED101 - Operatore di magazzino')
    expect(screen.queryByRole('button', { name: 'Scollega' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Sincronizza' })).not.toBeInTheDocument()
  })
})
