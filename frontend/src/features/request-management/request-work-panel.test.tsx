import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { RequestWorkPanelScreen } from '@/features/request-management/request-work-panel'
import { FULL_PERMISSIONS, workPanel as panel } from '@/features/request-management/request-work-panel-fixtures'

/**
 * Spec 0049 AC-061: the work panel renders the read-only context and mounts
 * `ContactsManager` for both Registry and Referent.
 */

const fetchRequestWorkPanelMock = vi.fn()
const updateRequestWorkMock = vi.fn()
vi.mock('@/features/request-management/api', () => ({
  fetchRequestWorkPanel: (...args: unknown[]) => fetchRequestWorkPanelMock(...args),
  updateRequestWork: (...args: unknown[]) => updateRequestWorkMock(...args),
}))

const createContactMock = vi.fn()
const updateContactMock = vi.fn()
const deleteContactMock = vi.fn()
vi.mock('@/features/personal-data/api', () => ({
  createContact: (...args: unknown[]) => createContactMock(...args),
  updateContact: (...args: unknown[]) => updateContactMock(...args),
  deleteContact: (...args: unknown[]) => deleteContactMock(...args),
}))

// Stubs the inline editor (mirrors `contacts-manager.test.tsx`) so the "add a
// contact" flow can be driven without the config (enum options) query the
// real type select needs: submits a fixed phone contact.
vi.mock('@/features/personal-data/contact-form', () => ({
  ContactForm: ({ onSubmit }: { onSubmit: (fields: Record<string, unknown>) => void }) => (
    <button
      type="button"
      data-testid="stub-submit"
      onClick={() => onSubmit({ type: 'phone', value: '+39 02 1234567', label: null, is_primary: false })}
    >
      stub-save
    </button>
  ),
}))

// The panel now hosts the collaboration card, which reads the actor's client
// abilities to gate its Documents tab: stub them, this suite has no AuthProvider.
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

/**
 * The row's category picker reads the category TREE (user directive
 * 2026-08-03), which is where the persisted category's LABEL now comes from —
 * the fixture below mirrors the panel's saved pair (`Sales` / `Consulting`,
 * category 500), so the assertion on the rendered trigger stays a real one
 * against the real component.
 */
const { CATEGORY_TREE } = vi.hoisted(() => ({
  CATEGORY_TREE: [
    {
      id: 400,
      name: 'Formazione',
      parent_id: null,
      attributes_count: 0,
      products_count: 0,
      business_function_id: 40,
      requires_quote: false,
      is_selectable: false,
      management_mode: 'multiple' as const,
      children: [
        {
          id: 500,
          name: 'Consulting',
          parent_id: 400,
          attributes_count: 0,
          products_count: 0,
          business_function_id: null,
          requires_quote: false,
          is_selectable: true,
          management_mode: 'multiple' as const,
          children: [],
        },
      ],
    },
  ],
}))

vi.mock('@/features/product-categories/use-product-category-tree', () => ({
  useProductCategoryTree: () => ({
    data: CATEGORY_TREE,
    isPending: false,
    isError: false,
    refetch: vi.fn(),
  }),
}))

// Stubs the timeline (its own behavior has its own suite) so the history tab can
// be opened without the activity-log query.
const activityLogSectionMock = vi.fn()
vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: (props: { resource: string; id: number }) => {
    activityLogSectionMock(props)
    return <div>{`activity-log:${props.resource}:${props.id}`}</div>
  },
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

function renderPanel(id = 4001) {
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
  createContactMock.mockReset()
  updateContactMock.mockReset()
  deleteContactMock.mockReset()
  activityLogSectionMock.mockReset()
})

describe('RequestWorkPanelScreen (spec 0049 AC-061)', () => {
  it('renders the compact context header and the always-active client fields', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())

    renderPanel()

    await waitFor(() => expect(screen.getByRole('heading', { name: 'Preliminary information' })).toBeInTheDocument())

    // Read-only context, now a compact header strip.
    expect(screen.getByText('Acme S.p.A.')).toBeInTheDocument()
    expect(screen.getByText('New')).toBeInTheDocument()

    // Funzione aziendale + categoria prodotto: an EDITOR since the user
    // directive 2026-07-31 (it used to be a read-only badge in the summary),
    // prefilled with the persisted pair.
    expect(screen.getByRole('combobox', { name: 'Business function 1' })).toHaveTextContent('Sales')
    expect(screen.getByRole('combobox', { name: 'Product category 1' })).toHaveTextContent('Consulting')

    // Anagrafica: the client's channels are ACTIVE, prefilled inputs — not a
    // read-only list behind an "edit" dialog.
    expect(screen.getByLabelText('Email')).toHaveValue('client@acme.test')
    expect(screen.getByLabelText('Phone')).toHaveValue('')
    expect(screen.getByLabelText('PEC')).toBeInTheDocument()
    expect(screen.getByLabelText('Fax')).toBeInTheDocument()
    // ...while the address group folds away, closed on arrival (user directive
    // 2026-09-10): its own toggle opens it.
    expect(screen.queryByLabelText(/^Address\*?$/)).not.toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: /^Address$/ }))
    expect(screen.getByLabelText(/^Address\*?$/)).toHaveValue('')

    // Spec 0056: the operational site is exposed and editable — from its own
    // card above the Team since the user directive 2026-09-10.
    expect(screen.getByRole('combobox', { name: 'Operational site' })).toBeInTheDocument()
  })

  it('sends a contact typed in the inline field with the single save, no per-field persistence', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())
    updateRequestWorkMock.mockResolvedValue(panel())

    renderPanel()

    await waitFor(() => expect(screen.getByLabelText('Email')).toHaveValue('client@acme.test'))

    fireEvent.change(screen.getByLabelText('Phone'), { target: { value: '+39 02 1234567' } })
    fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateRequestWorkMock).toHaveBeenCalledTimes(1))
    // The whole client set travels in the panel's own PATCH; the per-contact
    // endpoints are never touched from this screen.
    expect(updateRequestWorkMock.mock.calls[0][1]).toEqual({
      client_contacts: [
        { id: 1, type: 'email', value: 'client@acme.test', label: null, is_primary: true },
        { type: 'phone', value: '+39 02 1234567', label: null, is_primary: true },
      ],
    })
    expect(createContactMock).not.toHaveBeenCalled()
  })

  it('sends the client address created inline with the same save', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())
    updateRequestWorkMock.mockResolvedValue(panel())

    renderPanel()

    // The address group arrives closed (user directive 2026-09-10): open it,
    // then type into it.
    await waitFor(() => expect(screen.getByRole('button', { name: /^Address$/ })).toBeInTheDocument())
    fireEvent.click(screen.getByRole('button', { name: /^Address$/ }))

    fireEvent.change(screen.getByLabelText(/^Address\*?$/), { target: { value: 'Via Roma 1' } })
    fireEvent.change(screen.getByLabelText('Postal code'), { target: { value: '20100' } })
    fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateRequestWorkMock).toHaveBeenCalledTimes(1))
    expect(updateRequestWorkMock.mock.calls[0][1]).toMatchObject({
      client_address: { line1: 'Via Roma 1', postal_code: '20100' },
    })
  })

  it('sends the client identity edited inline with the same save', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())
    updateRequestWorkMock.mockResolvedValue(panel())

    renderPanel()

    // The identity block is prefilled from the client's card, right in the
    // anagraphic section — not behind the Registries module.
    await waitFor(() => expect(screen.getByLabelText('VAT number')).toHaveValue('IT01234567897'))
    expect(screen.getByLabelText(/Company name/)).toHaveValue('Acme S.p.A.')

    fireEvent.change(screen.getByLabelText('Tax code'), { target: { value: '01234567897' } })
    fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateRequestWorkMock).toHaveBeenCalledTimes(1))
    // A full replace of the card's identity fields, no id: the server resolves
    // the card from the request's client.
    expect(updateRequestWorkMock.mock.calls[0][1]).toEqual({
      client_identity: {
        type: 'company',
        first_name: null,
        last_name: null,
        company_name: 'Acme S.p.A.',
        tax_code: '01234567897',
        vat_number: 'IT01234567897',
        sdi_code: null,
        birth_date: null,
        birth_city_id: null,
        residence_city_id: null,
        gender: null,
      },
    })
  })

  it('shows the load error state with a retry action when the fetch fails', async () => {
    fetchRequestWorkPanelMock.mockRejectedValue(new Error('network down'))

    renderPanel()

    await waitFor(() => expect(screen.getByRole('alert')).toHaveTextContent('Could not load the record.'))
    expect(screen.getByRole('button', { name: 'Retry' })).toBeInTheDocument()
  })
})

describe('RequestWorkPanelScreen — activity log tab (spec 0049 D-7 amended)', () => {
  it('offers the history tab when the actor holds view_activity, mounting the timeline only once selected', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(
      panel({ permissions: { ...FULL_PERMISSIONS, actions: { view_activity: true } } }),
    )

    renderPanel()

    const tab = await screen.findByRole('tab', { name: 'History' })
    // Notes is the default surface: the timeline is not mounted until asked for.
    expect(tab).toHaveAttribute('aria-selected', 'false')
    expect(activityLogSectionMock).not.toHaveBeenCalled()

    // Radix `TabsTrigger` activates on `mouseDown`, not `click`.
    fireEvent.mouseDown(tab)

    // The Opportunity id (spec 0086 D-9), NOT the panel's own (Offerta) id —
    // the fixture sets the two deliberately apart so an inverted wiring fails.
    expect(await screen.findByText('activity-log:request-management:8001')).toBeInTheDocument()
    expect(activityLogSectionMock).toHaveBeenCalledWith(
      expect.objectContaining({ resource: 'request-management', id: 8001 }),
    )
  })

  it('hides the history tab when the action is denied', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(
      panel({ permissions: { ...FULL_PERMISSIONS, actions: { view_activity: false } } }),
    )

    renderPanel()

    await waitFor(() => expect(screen.getByRole('heading', { name: 'Preliminary information' })).toBeInTheDocument())
    expect(screen.queryByRole('tab', { name: 'History' })).not.toBeInTheDocument()
  })

  /**
   * Directive 2026-07-27: "Note generali" highlighted in the side column —
   * EDITABLE since the direttiva utente 2026-09-09, so the persisted note is
   * the field's own value. Its write path has its own suite
   * (request-general-notes-field.test.tsx).
   */
  it('shows the general notes in the side column field', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(
      panel({ general_notes: 'Recall the client in September' }),
    )

    renderPanel()

    expect(await screen.findByRole('textbox', { name: 'General notes' })).toHaveValue(
      'Recall the client in September',
    )
  })
})
