import type { ReactNode } from 'react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'
import { LearnerStatementAction } from '@/integrations/formalab/learner-statement/learner-statement-action'
import { buildLearner, buildStatement } from '@/integrations/formalab/learner-statement/test-fixtures'
import { buildLink, buildWorkOrderLink, createWrapper } from '@/integrations/formalab/test-support'

const fetchLinkMock = vi.fn()
const fetchStatementMock = vi.fn()
const updateCommissionMock = vi.fn()
const downloadMock = vi.fn()

vi.mock('@/integrations/formalab/api', () => ({
  fetchFormalabWorkOrderLink: (id: number) => fetchLinkMock(id),
  syncFormalabWorkOrderLink: vi.fn(),
}))
vi.mock('@/integrations/formalab/learner-statement/api', () => ({
  fetchLearnerStatement: (...args: unknown[]) => fetchStatementMock(...args),
  updateLearnerCommission: (...args: unknown[]) => updateCommissionMock(...args),
  updatePartnerSupplier: vi.fn(),
  downloadLearnerStatement: (...args: unknown[]) => downloadMock(...args),
}))
vi.mock('@/features/for-select/api', () => ({
  fetchForSelect: vi.fn().mockResolvedValue({ items: [], pagination: { total: 0, offset: 0, limit: 20, total_pages: 0 } }),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const WORK_ORDER = { id: 7 } as WorkOrderDetailWithPermissions

function renderAction() {
  const { Wrapper } = createWrapper()
  const wrapper = ({ children }: { children: ReactNode }) => (
    <MemoryRouter>
      <Wrapper>{children}</Wrapper>
    </MemoryRouter>
  )
  return render(<LearnerStatementAction workOrder={WORK_ORDER} />, { wrapper })
}

describe('LearnerStatementAction', () => {
  beforeEach(async () => {
    await i18n.changeLanguage('it')
    vi.clearAllMocks()
  })

  it('stays hidden when the work order is not eligible or has no class linked', async () => {
    fetchLinkMock.mockResolvedValue(buildWorkOrderLink({ eligible: true, link: null }))
    renderAction()

    await waitFor(() => expect(fetchLinkMock).toHaveBeenCalledWith(7))
    expect(screen.queryByRole('button', { name: 'Prospetto contabile discenti' })).not.toBeInTheDocument()
  })

  it('opens the prospetto with summary, learners and partners, reading Formalab only once opened', async () => {
    fetchLinkMock.mockResolvedValue(buildWorkOrderLink({ link: buildLink() }))
    fetchStatementMock.mockResolvedValue(buildStatement())
    renderAction()

    fireEvent.click(await screen.findByRole('button', { name: 'Prospetto contabile discenti' }))

    const dialog = await screen.findByRole('dialog')
    expect(fetchStatementMock).toHaveBeenCalledWith(7, null)
    expect(await within(dialog).findByText('Riepilogo Costi e Ricavi')).toBeInTheDocument()
    expect(within(dialog).getByText('SCENARIO ECONOMICO')).toBeInTheDocument()
    expect(within(dialog).getByText('Rossi')).toBeInTheDocument()
    expect(within(dialog).getByText('Riepilogo Provvigioni per Partner Commerciale')).toBeInTheDocument()
    expect(within(dialog).getByRole('radio', { name: 'Finanziario' })).toBeDisabled()
  })

  it('saves a fixed commission on blur and shows the recomputed prospetto', async () => {
    fetchLinkMock.mockResolvedValue(buildWorkOrderLink({ link: buildLink() }))
    fetchStatementMock.mockResolvedValue(buildStatement())
    updateCommissionMock.mockResolvedValue(buildStatement({ learners: [buildLearner({ amount: 200, commission: 200 })] }))
    renderAction()

    fireEvent.click(await screen.findByRole('button', { name: 'Prospetto contabile discenti' }))
    const input = await screen.findByRole('spinbutton', { name: 'Importo provvigione di Maria Rossi' })
    fireEvent.change(input, { target: { value: '200' } })
    fireEvent.blur(input)

    await waitFor(() => expect(updateCommissionMock).toHaveBeenCalledWith(7, 'RSSMRA80A01H501X', { amount: 200 }, 'economic'))
    expect(await screen.findByDisplayValue('200')).toBeInTheDocument()
  })

  it('warns when Formalab cannot give the class', async () => {
    fetchLinkMock.mockResolvedValue(buildWorkOrderLink({ link: buildLink() }))
    fetchStatementMock.mockResolvedValue(buildStatement({ class_status: 'unavailable', class: null, learners: [] }))
    renderAction()

    fireEvent.click(await screen.findByRole('button', { name: 'Prospetto contabile discenti' }))

    expect(await screen.findByText('Dati classe non disponibili.')).toBeInTheDocument()
    expect(screen.queryByText('Elenco Discenti')).not.toBeInTheDocument()
  })

  it('exports the whole statement and the per-partner workbook', async () => {
    fetchLinkMock.mockResolvedValue(buildWorkOrderLink({ link: buildLink() }))
    fetchStatementMock.mockResolvedValue(buildStatement())
    downloadMock.mockResolvedValue(undefined)
    renderAction()

    fireEvent.click(await screen.findByRole('button', { name: 'Prospetto contabile discenti' }))
    await screen.findByText('Riepilogo Costi e Ricavi')
    fireEvent.click(screen.getByRole('button', { name: 'Esporta Excel' }))
    await waitFor(() => expect(downloadMock).toHaveBeenCalledWith(7, 'full', 'economic'))
    fireEvent.click(screen.getByRole('button', { name: 'Esporta per Partner' }))
    await waitFor(() => expect(downloadMock).toHaveBeenCalledWith(7, 'partners', 'economic'))
  })
})
