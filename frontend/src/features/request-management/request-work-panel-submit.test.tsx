import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { RequestWorkPanelScreen } from '@/features/request-management/request-work-panel'
import { workPanel as panel } from '@/features/request-management/request-work-panel-fixtures'
import type { RequestWorkPanelWithPermissions } from '@/features/request-management/types'
import type { FieldChangeRequestResource } from '@/features/field-change-requests/types'

/**
 * What the work panel does with a submit it cannot send.
 *
 * The endpoint is SPARSE: `UpdateRequestRequest` marks every key `sometimes`,
 * so a block the operator did not touch never travels and is never validated
 * server-side. Mirroring a mandatory rule unconditionally client-side was
 * therefore STRICTER than the server: on a legacy record (no product of
 * interest, an Attribute made required after the fact, a card whose VAT number
 * fails the control digit) `handleSubmit` refused every save before any
 * request went out — and refused it silently, which read as a dead button.
 *
 * Split out of `request-work-panel.test.tsx` (hard limit 500 lines).
 */

const fetchRequestWorkPanelMock = vi.fn()
const updateRequestWorkMock = vi.fn()
vi.mock('@/features/request-management/api', () => ({
  fetchRequestWorkPanel: (...args: unknown[]) => fetchRequestWorkPanelMock(...args),
  updateRequestWork: (...args: unknown[]) => updateRequestWorkMock(...args),
}))

// Stubs the inline contact editor (mirrors the sibling suite) so the quick
// fields work without the config query the real type select needs.
vi.mock('@/features/personal-data/contact-form', () => ({
  ContactForm: () => null,
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: () => null,
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const approveFieldChangeRequestMock = vi.fn()
vi.mock('@/features/field-change-requests/api', () => ({
  fetchFieldChangeRequestsForRecord: () => Promise.resolve([sourceChangeRequest()]),
  approveFieldChangeRequest: (...args: unknown[]) => approveFieldChangeRequestMock(...args),
  rejectFieldChangeRequest: vi.fn(),
}))

/** The one pending proposal the record carries in this suite: the Fonte, decidable by this actor. */
function sourceChangeRequest(
  overrides: Partial<FieldChangeRequestResource> = {},
): FieldChangeRequestResource {
  return {
    id: 12,
    resource: 'request-management',
    resource_label: 'navigation.requestManagement',
    subject_id: 1,
    subject_label: 'OPP_1',
    subject_path: '/request-management/1',
    field: 'source_id',
    field_label: 'requestManagement.columns.source',
    current_value: 30,
    current_label: 'Web',
    requested_value: 7,
    requested_label: 'Passaparola',
    reason: 'Referral confermato.',
    status: 'pending',
    requested_by: { id: 8, name: 'Mario Rossi' },
    requested_at: '2026-08-03T10:12:00.000000Z',
    handled_by: null,
    handled_at: null,
    handling_note: null,
    can: { approve: true, reject: true },
    ...overrides,
  }
}

/** A company card whose VAT number fails the control digit, as imported data often is. */
function legacyVatPanel(): RequestWorkPanelWithPermissions {
  const base = panel()

  return {
    ...base,
    client_identity: { ...base.client_identity!, vat_number: 'IT01234567890' },
  }
}

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

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchRequestWorkPanelMock.mockReset()
  updateRequestWorkMock.mockReset()
})

describe('RequestWorkPanelScreen — a submit the panel cannot send', () => {

  /**
   * User directive 2026-08-03: the panel is long, so the same save closes the
   * editable form at its foot. It is a COPY of the header's, not a second
   * behaviour — same form, same "nothing to send yet" gate — and it carries no
   * cancel, since this screen edits a persisted record.
   */
  it('repeats the save at the foot of the form, on the same gate as the header', async () => {
    const stored = panel()
    fetchRequestWorkPanelMock.mockResolvedValue(stored)
    updateRequestWorkMock.mockResolvedValue(stored)

    renderPanel()

    await waitFor(() => expect(screen.getByLabelText('Email')).toHaveValue('client@acme.test'))

    const [headerSave, footerSave] = screen.getAllByRole('button', { name: 'Save' })
    expect(screen.getByRole('banner')).not.toContainElement(footerSave)
    expect(footerSave).toHaveAttribute('form', headerSave.getAttribute('form'))
    // Nothing edited yet: both copies are unavailable.
    expect(footerSave).toBeDisabled()
    expect(screen.queryByRole('button', { name: 'Cancel' })).not.toBeInTheDocument()

    fireEvent.change(screen.getByLabelText('Phone'), { target: { value: '+39 02 1234567' } })
    fireEvent.click(screen.getAllByRole('button', { name: 'Save' })[1])

    await waitFor(() => expect(updateRequestWorkMock).toHaveBeenCalled())
  })

  /**
   * A submit refused by the client schema used to be SILENT: `handleSubmit`
   * dropped it, the save button stayed as it was and the blocking fields
   * reported nothing where the operator was looking — the button read as
   * broken. The refusal is now stated next to the button itself.
   */
  it('states why a blocked save did not go through, next to the save button', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())

    renderPanel()

    await waitFor(() => expect(screen.getByLabelText('Email')).toHaveValue('client@acme.test'))

    // The mandatory rule is broken by an ACTUAL edit: the last product of interest is dropped.
    fireEvent.click(screen.getByRole('button', { name: 'Remove product Fibra 1000' }))
    fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))

    const header = screen.getByRole('banner')
    const alert = await within(header).findByRole('alert')
    // The offending block is NAMED.
    expect(alert).toHaveTextContent('Products of interest')
    expect(updateRequestWorkMock).not.toHaveBeenCalled()
  })

  /**
   * Funzione aziendale + categoria prodotto are editable since the user
   * directive 2026-07-31, under the same "never empty" rule the server
   * enforces (`min:1`): emptying the collection is refused before the request
   * goes out, and the summary names the block.
   */
  it('refuses a save that would leave the request without a product line', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())

    renderPanel()

    await waitFor(() =>
      expect(screen.getByRole('combobox', { name: 'Business function 1' })).toHaveTextContent('Sales'),
    )

    fireEvent.click(screen.getByRole('button', { name: 'Remove product line' }))
    fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))

    const alert = await within(screen.getByRole('banner')).findByRole('alert')
    expect(alert).toHaveTextContent('Product lines')
    expect(updateRequestWorkMock).not.toHaveBeenCalled()
  })

  /**
   * The counterpart, and the reason that rule is gated at all: a record that
   * legitimately has no product of interest must stay savable for any
   * UNRELATED edit — the endpoint is sparse, so the key is neither sent nor
   * validated server-side. Making it unconditional client-side refused every
   * save with no request ever going out, i.e. the save button did nothing.
   */
  it('saves an unrelated edit on a record that is missing a mandatory value', async () => {
    const stored = panel({ products_of_interest: [] })
    fetchRequestWorkPanelMock.mockResolvedValue(stored)
    updateRequestWorkMock.mockResolvedValue(stored)

    renderPanel()

    await waitFor(() => expect(screen.getByLabelText('Email')).toHaveValue('client@acme.test'))

    fireEvent.change(screen.getByLabelText('Phone'), { target: { value: '+39 02 1234567' } })
    fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateRequestWorkMock).toHaveBeenCalled())
    const [, payload] = updateRequestWorkMock.mock.calls[0]
    expect(payload).not.toHaveProperty('products_of_interest')
  })

  /**
   * Same gate for the three buffered client blocks. A legacy card carrying a
   * VAT number that fails the control digit (routine on imported data) used to
   * refuse EVERY save of the panel, naming a group whose fields all look
   * filled in — the server never even receives the block.
   */
  it('saves an unrelated edit while the untouched client card carries an invalid VAT number', async () => {
    const stored = legacyVatPanel()
    fetchRequestWorkPanelMock.mockResolvedValue(stored)
    updateRequestWorkMock.mockResolvedValue(stored)

    renderPanel()

    await waitFor(() => expect(screen.getByLabelText('Email')).toHaveValue('client@acme.test'))

    fireEvent.change(screen.getByLabelText('Phone'), { target: { value: '+39 02 1234567' } })
    fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateRequestWorkMock).toHaveBeenCalled())
    expect(updateRequestWorkMock.mock.calls[0][1]).not.toHaveProperty('client_identity')
  })

  /**
   * ...and once the operator DOES edit that card, the block travels, so the
   * rule applies again — with the message spelled out in the summary, since
   * `PersonalDataCardForm` renders none of its own.
   */
  it('spells out why the client card is refused once it is edited', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(legacyVatPanel())

    renderPanel()

    await waitFor(() => expect(screen.getByLabelText(/Company name/)).toHaveValue('Acme S.p.A.'))

    fireEvent.change(screen.getByLabelText(/Company name/), { target: { value: 'Acme S.r.l.' } })
    fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))

    const alert = await within(screen.getByRole('banner')).findByRole('alert')
    expect(alert).toHaveTextContent('Identity')
    expect(alert).toHaveTextContent('The VAT number is not valid.')
    expect(updateRequestWorkMock).not.toHaveBeenCalled()
  })

  /**
   * A change request approved from the record's own card (spec 0078, user
   * directive 2026-08-04) writes the protected field server-side: the panel
   * has to refetch, or the Fonte control keeps the pre-approval value and the
   * sparse payload sends it straight back on the next save — silently undoing
   * the approval the operator just granted.
   */
  it('does not send back the pre-approval Fonte after approving a change request on it', async () => {
    const stored = panel()
    const approved: RequestWorkPanelWithPermissions = {
      ...stored,
      source_id: 7,
      source: { id: 7, name: 'Passaparola' },
    }
    fetchRequestWorkPanelMock.mockResolvedValueOnce(stored).mockResolvedValue(approved)
    updateRequestWorkMock.mockResolvedValue(approved)
    approveFieldChangeRequestMock.mockResolvedValue(
      sourceChangeRequest({ status: 'approved', can: { approve: false, reject: false } }),
    )

    renderPanel()

    await waitFor(() => expect(screen.getByRole('button', { name: 'Approve' })).toBeInTheDocument())
    fireEvent.click(screen.getByRole('button', { name: 'Approve' }))
    fireEvent.click(screen.getByRole('button', { name: 'Confirm' }))

    await waitFor(() => expect(fetchRequestWorkPanelMock).toHaveBeenCalledTimes(2))
    await waitFor(() => expect(screen.getByLabelText('Email')).toHaveValue('client@acme.test'))

    fireEvent.change(screen.getByLabelText('Phone'), { target: { value: '+39 02 1234567' } })
    fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateRequestWorkMock).toHaveBeenCalled())
    expect(updateRequestWorkMock.mock.calls[0][1]).not.toHaveProperty('source_id')
  })
})
