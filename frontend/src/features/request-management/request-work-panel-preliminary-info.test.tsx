import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { RequestWorkPanelScreen } from '@/features/request-management/request-work-panel'
import { workPanel as panel } from '@/features/request-management/request-work-panel-fixtures'

/**
 * User directive 2026-08-07: the work panel carries the Offerta's own
 * "Informazioni aggiuntive" and "Stato di lavorazione", rendered by the SAME
 * components the Offerte form mounts (`QuoteDynamicFieldsSection`,
 * `QuoteWorkflowStatusField`).
 */

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

const TEXT_ATTRIBUTE = {
  id: 1,
  code: 'preferred_slot',
  name: 'Fascia oraria preferita',
  type: 'text',
  description: null,
  help_text: null,
  placeholder: null,
  icon: null,
  config: null,
  relation_target: null,
  is_required: false,
  sort_order: 0,
  options: [],
}

/** The row the request holds, plus a second one it can be advanced to. */
const STATUS_OPEN = {
  id: 900,
  name: 'Aperto',
  color: 'slate',
  description: null,
  group: 'open',
  requires_note: false,
}

function statusRequiringNote(requiresNote: boolean) {
  return {
    id: 901,
    name: 'In lavorazione',
    color: 'amber',
    description: null,
    group: 'open',
    requires_note: requiresNote,
  }
}

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

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  fetchRequestWorkPanelMock.mockReset()
  updateRequestWorkMock.mockReset()
})

describe('work panel — Informazioni aggiuntive', () => {
  it('renders a field per applicable attribute, prefilled with the stored value', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(
      panel({
        applicable_attributes: [TEXT_ATTRIBUTE],
        attribute_values: { preferred_slot: 'mattina' },
      }),
    )

    renderPanel()

    const field = await screen.findByLabelText(/Fascia oraria preferita/)
    expect(field).toHaveValue('mattina')
  })

  it('sends only the changed dynamic values on save', async () => {
    const loaded = panel({
      applicable_attributes: [TEXT_ATTRIBUTE],
      attribute_values: { preferred_slot: 'mattina' },
    })
    fetchRequestWorkPanelMock.mockResolvedValue(loaded)
    updateRequestWorkMock.mockResolvedValue(loaded)

    renderPanel()

    fireEvent.change(await screen.findByLabelText(/Fascia oraria preferita/), {
      target: { value: 'pomeriggio' },
    })
    fireEvent.click(screen.getAllByRole('button', { name: 'Salva' })[0])

    await waitFor(() => expect(updateRequestWorkMock).toHaveBeenCalled())
    expect(updateRequestWorkMock.mock.calls[0][1]).toEqual({
      attribute_values: { preferred_slot: 'pomeriggio' },
    })
  })

  it('shows the empty state when the categories carry no attribute', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())

    renderPanel()

    expect(
      await screen.findByText('Nessun campo aggiuntivo per i prodotti selezionati.'),
    ).toBeInTheDocument()
  })
})

describe('work panel — Stato di lavorazione', () => {
  it('renders the resolved set and sends the advance on save', async () => {
    const target = statusRequiringNote(false)
    const loaded = panel({ quote_workflow_statuses: [STATUS_OPEN, target] })
    fetchRequestWorkPanelMock.mockResolvedValue(loaded)
    updateRequestWorkMock.mockResolvedValue(loaded)

    renderPanel()

    fireEvent.click(await screen.findByRole('combobox', { name: /^Stato/ }))
    fireEvent.click(await screen.findByRole('option', { name: /In lavorazione/ }))
    fireEvent.click(screen.getAllByRole('button', { name: 'Salva' })[0])

    await waitFor(() => expect(updateRequestWorkMock).toHaveBeenCalled())
    expect(updateRequestWorkMock.mock.calls[0][1]).toEqual({ quote_workflow_status_id: 901 })
  })

  it('demands the transition note before submitting when the target requires one', async () => {
    const target = statusRequiringNote(true)
    fetchRequestWorkPanelMock.mockResolvedValue(panel({ quote_workflow_statuses: [STATUS_OPEN, target] }))

    renderPanel()

    fireEvent.click(await screen.findByRole('combobox', { name: /^Stato/ }))
    fireEvent.click(await screen.findByRole('option', { name: /In lavorazione/ }))
    fireEvent.click(screen.getAllByRole('button', { name: 'Salva' })[0])

    expect(
      await screen.findByText('È obbligatoria una nota per passare a questo stato.'),
    ).toBeInTheDocument()
    expect(updateRequestWorkMock).not.toHaveBeenCalled()
  })

  it('sends the note alongside the status once it is filled in', async () => {
    const target = statusRequiringNote(true)
    const loaded = panel({ quote_workflow_statuses: [STATUS_OPEN, target] })
    fetchRequestWorkPanelMock.mockResolvedValue(loaded)
    updateRequestWorkMock.mockResolvedValue(loaded)

    renderPanel()

    fireEvent.click(await screen.findByRole('combobox', { name: /^Stato/ }))
    fireEvent.click(await screen.findByRole('option', { name: /In lavorazione/ }))
    fireEvent.change(await screen.findByLabelText(/Nota/), { target: { value: 'Cliente ricontattato.' } })
    fireEvent.click(screen.getAllByRole('button', { name: 'Salva' })[0])

    await waitFor(() => expect(updateRequestWorkMock).toHaveBeenCalled())
    expect(updateRequestWorkMock.mock.calls[0][1]).toEqual({
      quote_workflow_status_id: 901,
      note: 'Cliente ricontattato.',
    })
  })
})
