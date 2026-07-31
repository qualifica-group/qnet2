import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { RequestWorkPanelScreen } from '@/features/request-management/request-work-panel'
import { workPanel as panel } from '@/features/request-management/request-work-panel-fixtures'
import type { RequestWorkPanelWithPermissions } from '@/features/request-management/types'

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
   * A submit refused by the client schema used to be SILENT: `handleSubmit`
   * dropped it, the save button stayed as it was and the blocking fields
   * reported nothing where the operator was looking — the button read as
   * broken. The refusal is now stated next to the button itself.
   */
  it('states why a blocked save did not go through, next to the save button', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())

    renderPanel()

    await waitFor(() => expect(screen.getByRole('textbox', { name: 'Notes' })).toHaveValue('Some notes'))

    // Both mandatory rules are broken by an ACTUAL edit: the required
    // Attribute is emptied and the last product of interest dropped.
    fireEvent.change(screen.getByRole('textbox', { name: 'Notes' }), { target: { value: '' } })
    fireEvent.click(screen.getByRole('button', { name: 'Remove product Fibra 1000' }))
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    const header = screen.getByRole('banner')
    const alert = await within(header).findByRole('alert')
    // The offending blocks are NAMED — the required Attribute by its own name,
    // not by the block label, since a layout can hide the leaf control.
    expect(alert).toHaveTextContent('Products of interest')
    expect(alert).toHaveTextContent('Notes')
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
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    const alert = await within(screen.getByRole('banner')).findByRole('alert')
    expect(alert).toHaveTextContent('Product lines')
    expect(updateRequestWorkMock).not.toHaveBeenCalled()
  })

  /**
   * The counterpart, and the reason those two rules are gated at all: a record
   * that legitimately has no product of interest (or an empty required
   * Attribute) must stay savable for any UNRELATED edit — the endpoint is
   * sparse, so those keys are neither sent nor validated server-side. Making
   * them unconditional client-side refused every save with no request ever
   * going out, i.e. the save button did nothing.
   */
  it('saves an unrelated edit on a record that is missing a mandatory value', async () => {
    const stored = panel({ products_of_interest: [], attribute_values: { notes: null, priority: null } })
    fetchRequestWorkPanelMock.mockResolvedValue(stored)
    updateRequestWorkMock.mockResolvedValue(stored)

    renderPanel()

    await waitFor(() => expect(screen.getByLabelText('Email')).toHaveValue('client@acme.test'))

    fireEvent.change(screen.getByLabelText('Phone'), { target: { value: '+39 02 1234567' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateRequestWorkMock).toHaveBeenCalled())
    const [, payload] = updateRequestWorkMock.mock.calls[0]
    expect(payload).not.toHaveProperty('products_of_interest')
    expect(payload).not.toHaveProperty('attribute_values')
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
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

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
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    const alert = await within(screen.getByRole('banner')).findByRole('alert')
    expect(alert).toHaveTextContent('Identity')
    expect(alert).toHaveTextContent('The VAT number is not valid.')
    expect(updateRequestWorkMock).not.toHaveBeenCalled()
  })
})
