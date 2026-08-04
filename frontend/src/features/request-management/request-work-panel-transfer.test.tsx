import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { RequestWorkPanelScreen } from '@/features/request-management/request-work-panel'
import { FULL_PERMISSIONS, workPanel as panel } from '@/features/request-management/request-work-panel-fixtures'

/**
 * "Trasferisci contatto" button repeated next to Save in the work panel
 * (spec 0079 addendum, user directive): the sticky identity bar AND the
 * footer action bar, both gated on `canAction('transfer_contact')` and both
 * driving the SAME `AssignOperatorsDialog` the table's row/bulk action uses
 * (`request-management-table-transfer.test.tsx` is the sibling suite for
 * that surface). `TableView`/category tree/collaboration deps are stubbed
 * exactly as `request-work-panel.test.tsx` does — this suite only adds what
 * it needs on top: `transferRequests` and the dialog's own pickers.
 */

const fetchRequestWorkPanelMock = vi.fn()
const updateRequestWorkMock = vi.fn()
const transferRequestsMock = vi.fn()
vi.mock('@/features/request-management/api', () => ({
  fetchRequestWorkPanel: (...args: unknown[]) => fetchRequestWorkPanelMock(...args),
  updateRequestWork: (...args: unknown[]) => updateRequestWorkMock(...args),
  transferRequests: (...args: unknown[]) => transferRequestsMock(...args),
}))

vi.mock('@/features/personal-data/api', () => ({
  createContact: vi.fn(),
  updateContact: vi.fn(),
  deleteContact: vi.fn(),
}))

vi.mock('@/features/personal-data/contact-form', () => ({
  ContactForm: () => null,
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('@/features/product-categories/use-product-category-tree', () => ({
  useProductCategoryTree: () => ({ data: [], isPending: false, isError: false, refetch: vi.fn() }),
}))

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: () => null,
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

/** Mirrors `request-management-table-transfer.test.tsx`'s stub: a plain button per picker. */
const SITE_PICK_ID = 7
const OPERATOR_PICK_ID = 42
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    resource,
    value,
    onChange,
    labels,
  }: {
    resource: string
    value: number | null
    onChange: (value: number | null) => void
    labels: { triggerLabel: string }
  }) => (
    <button
      type="button"
      aria-label={labels.triggerLabel}
      onClick={() => onChange(resource === 'operational-sites' ? SITE_PICK_ID : OPERATOR_PICK_ID)}
    >
      {value ?? 'none'}
    </button>
  ),
}))

function renderPanel(id = 1) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>
        <RequestWorkPanelScreen id={id} />
      </ConfirmDialogProvider>
    </QueryClientProvider>,
  )
}

/** Full flow: the (already visible) dialog, pick Sede then Operatore, confirm. */
function fillAndConfirm() {
  fireEvent.click(screen.getByRole('button', { name: 'Site' }))
  fireEvent.click(screen.getByRole('button', { name: 'Operator' }))
  fireEvent.click(screen.getByRole('button', { name: 'Assign' }))
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchRequestWorkPanelMock.mockReset()
  updateRequestWorkMock.mockReset()
  transferRequestsMock.mockReset()
  vi.mocked(toast.success).mockClear()
  vi.mocked(toast.error).mockClear()
})

describe('RequestWorkPanelScreen — "Trasferisci contatto" button (spec 0079 addendum)', () => {
  it('shows the button in both the header and the footer when the actor can transfer', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(
      panel({ permissions: { ...FULL_PERMISSIONS, actions: { transfer_contact: true } } }),
    )

    renderPanel()

    await waitFor(() => expect(screen.getAllByRole('button', { name: 'Transfer contact' })).toHaveLength(2))
  })

  it('shows the button in neither place when the actor cannot transfer', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(
      panel({ permissions: { ...FULL_PERMISSIONS, actions: { transfer_contact: false } } }),
    )

    renderPanel()

    await waitFor(() => expect(screen.getByRole('heading', { name: 'Preliminary information' })).toBeInTheDocument())
    expect(screen.queryByRole('button', { name: 'Transfer contact' })).not.toBeInTheDocument()
  })

  it('shows the button in neither place when the server declares transfer_contact false (its own update+transferContact double gate, e.g. an actor with transferContact but no update)', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(
      panel({
        permissions: {
          ...FULL_PERMISSIONS,
          resource: { ...FULL_PERMISSIONS.resource, update: true },
          actions: { transfer_contact: false },
        },
      }),
    )

    renderPanel()

    await waitFor(() => expect(screen.getByRole('heading', { name: 'Preliminary information' })).toBeInTheDocument())
    expect(screen.queryByRole('button', { name: 'Transfer contact' })).not.toBeInTheDocument()
  })

  it('opens the shared dialog, skipping the mode step, from either button', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(
      panel({ permissions: { ...FULL_PERMISSIONS, actions: { transfer_contact: true } } }),
    )

    renderPanel()

    const [headerButton] = await screen.findAllByRole('button', { name: 'Transfer contact' })
    fireEvent.click(headerButton)

    expect(screen.getByRole('dialog')).toBeInTheDocument()
    expect(screen.queryByRole('radio', { name: 'Balanced split' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Site' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Operator' })).toBeInTheDocument()
  })

  it('transfers the single record, toasts and refreshes the panel on success', async () => {
    transferRequestsMock.mockResolvedValue({ transferred: 1 })
    fetchRequestWorkPanelMock.mockResolvedValue(
      panel({ id: 9, permissions: { ...FULL_PERMISSIONS, actions: { transfer_contact: true } } }),
    )

    renderPanel(9)

    const [headerButton] = await screen.findAllByRole('button', { name: 'Transfer contact' })
    fireEvent.click(headerButton)
    fillAndConfirm()

    await waitFor(() =>
      expect(transferRequestsMock).toHaveBeenCalledWith({
        request_ids: [9],
        operational_site_id: SITE_PICK_ID,
        operator_id: OPERATOR_PICK_ID,
      }),
    )
    expect(toast.success).toHaveBeenCalledWith('1 request(s) transferred.')
    // The success handler invalidates the panel's own query (D-3 precedent,
    // request-attribution-section.tsx): an active query refetches on
    // invalidation, so the fetch fires again beyond the initial mount.
    await waitFor(() => expect(fetchRequestWorkPanelMock).toHaveBeenCalledTimes(2))
  })

  it('toasts the generic error and keeps the dialog open on failure', async () => {
    transferRequestsMock.mockRejectedValue(new Error('network down'))
    fetchRequestWorkPanelMock.mockResolvedValue(
      panel({ permissions: { ...FULL_PERMISSIONS, actions: { transfer_contact: true } } }),
    )

    renderPanel()

    const [, footerButton] = await screen.findAllByRole('button', { name: 'Transfer contact' })
    fireEvent.click(footerButton)
    fillAndConfirm()

    await waitFor(() => expect(transferRequestsMock).toHaveBeenCalledTimes(1))
    await waitFor(() =>
      expect(toast.error).toHaveBeenCalledWith('Unable to transfer the contact. Please try again.'),
    )
    expect(screen.getByRole('dialog')).toBeInTheDocument()
  })
})
