import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import axios, { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { RequestWorkPanelScreen } from '@/features/request-management/request-work-panel'
import { FULL_PERMISSIONS, workPanel as panel } from '@/features/request-management/request-work-panel-fixtures'

/**
 * Spec 0049 AC-061/062/063: the work panel renders the read-only context, a
 * control per applicable Attribute, the working-state select limited to the
 * resolved set, and mounts `ContactsManager` for both Registry and Referent.
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
  createContactMock.mockReset()
  updateContactMock.mockReset()
  deleteContactMock.mockReset()
  activityLogSectionMock.mockReset()
})

describe('RequestWorkPanelScreen (spec 0049 AC-061)', () => {
  it('renders the compact context header, the always-active client fields, the dynamic fields and the working state', async () => {
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
    // ...and so is the address: its group is always expanded, no toggle to open.
    expect(screen.queryByRole('button', { name: /^Address$/ })).not.toBeInTheDocument()
    expect(screen.getByLabelText('Address')).toHaveValue('')

    // One control per applicable attribute, by type.
    expect(screen.getByRole('textbox', { name: 'Notes' })).toHaveValue('Some notes')
    expect(screen.getByRole('combobox', { name: 'Priority' })).toHaveTextContent('Low')

    // Workflow status select, limited to the resolved set.
    expect(screen.getByRole('combobox', { name: 'Working status' })).toHaveTextContent('Open')

    // Spec 0056: the operational site is exposed and editable from the attribution section.
    expect(screen.getByRole('combobox', { name: 'Operational site' })).toBeInTheDocument()
  })

  it('sends a contact typed in the inline field with the single save, no per-field persistence', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())
    updateRequestWorkMock.mockResolvedValue(panel())

    renderPanel()

    await waitFor(() => expect(screen.getByLabelText('Email')).toHaveValue('client@acme.test'))

    fireEvent.change(screen.getByLabelText('Phone'), { target: { value: '+39 02 1234567' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

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

    // The address group is always expanded: type straight into it.
    await waitFor(() => expect(screen.getByLabelText('Address')).toBeInTheDocument())

    fireEvent.change(screen.getByLabelText('Address'), { target: { value: 'Via Roma 1' } })
    fireEvent.change(screen.getByLabelText('Postal code'), { target: { value: '20100' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

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
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

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

describe('RequestWorkPanelScreen — sparse submit (spec 0049 AC-062)', () => {
  it('sends only the changed workflow status', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())
    updateRequestWorkMock.mockResolvedValue(panel({ workflow_status: { id: 101, name: 'In progress', color: 'amber', system_key: null, description: null, requires_note: false } }))

    renderPanel()

    await waitFor(() =>
      expect(screen.getByRole('combobox', { name: 'Working status' })).toHaveTextContent('Open'),
    )

    fireEvent.click(screen.getByRole('combobox', { name: 'Working status' }))
    fireEvent.click(screen.getByRole('option', { name: 'In progress' }))
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateRequestWorkMock).toHaveBeenCalledTimes(1))
    const [id, payload] = updateRequestWorkMock.mock.calls[0]
    expect(id).toBe(1)
    expect(payload).toEqual({ opportunity_workflow_status_id: 101 })
  })

  it('maps a 422 on a dynamic field onto the field with the accessible-error triad', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())
    updateRequestWorkMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: {
          success: false,
          message: 'Validation failed',
          errors: { 'attribute_values.notes': ['Notes is required.'] },
        },
      } as never),
    )
    vi.spyOn(axios, 'isAxiosError').mockReturnValue(true)

    renderPanel()

    await waitFor(() => expect(screen.getByRole('textbox', { name: 'Notes' })).toBeInTheDocument())

    const notesField = screen.getByRole('textbox', { name: 'Notes' })
    fireEvent.change(notesField, { target: { value: 'Updated notes' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(screen.getByText('Notes is required.')).toBeInTheDocument())
    const message = screen.getByText('Notes is required.')
    expect(message).toHaveAttribute('role', 'alert')
    expect(notesField).toHaveAttribute('aria-invalid', 'true')
    expect(notesField).toHaveAttribute('aria-describedby', expect.stringContaining(message.id))

    vi.restoreAllMocks()
  })
})

describe('RequestWorkPanelScreen — bounded controls (spec 0049 AC-063)', () => {
  it('limits the workflow status select to the resolved set', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())

    renderPanel()

    await waitFor(() =>
      expect(screen.getByRole('combobox', { name: 'Working status' })).toHaveTextContent('Open'),
    )
    fireEvent.click(screen.getByRole('combobox', { name: 'Working status' }))

    const listbox = screen.getByRole('listbox')
    expect(within(listbox).getAllByRole('option')).toHaveLength(2)
    expect(within(listbox).getByRole('option', { name: 'Open' })).toBeInTheDocument()
    expect(within(listbox).getByRole('option', { name: 'In progress' })).toBeInTheDocument()
  })

  it('limits the enum attribute select to its own options', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())

    renderPanel()

    await waitFor(() => expect(screen.getByRole('combobox', { name: 'Priority' })).toBeInTheDocument())
    fireEvent.click(screen.getByRole('combobox', { name: 'Priority' }))

    const listbox = screen.getByRole('listbox')
    expect(within(listbox).getAllByRole('option')).toHaveLength(2)
    expect(within(listbox).getByRole('option', { name: 'Low' })).toBeInTheDocument()
    expect(within(listbox).getByRole('option', { name: 'High' })).toBeInTheDocument()
  })

  it('flags the required dynamic field in its label', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())

    renderPanel()

    await waitFor(() =>
      expect(screen.getByRole('textbox', { name: 'Notes' })).toBeInTheDocument(),
    )

    // The collaboration card renders its own "Notes" tab, so the bare text
    // query is ambiguous: keep the one that is a field label.
    const label = screen
      .getAllByText('Notes')
      .map((node) => node.closest('label'))
      .find((node): node is HTMLLabelElement => node !== null)

    expect(label).toBeDefined()
    expect(label).toHaveTextContent('*')
  })
})

describe('RequestWorkPanelScreen — resolved attribute layout (spec 0062 AC-015)', () => {
  it('renders the panel sectioned when the opportunity carries a resolved layout', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(
      panel({
        attribute_layout: {
          sections: [
            {
              id: 's1',
              title: 'Qualification',
              description: null,
              variant: 'default',
              collapsible: false,
              default_collapsed: false,
              columns: 1,
              sort_order: 0,
              rows: [{ id: 'r1', items: [{ attribute_code: 'notes', width: 'full' }] }],
            },
          ],
        },
      }),
    )

    renderPanel()

    expect(await screen.findByRole('heading', { name: 'Qualification' })).toBeInTheDocument()
    expect(screen.getByRole('textbox', { name: 'Notes' })).toHaveValue('Some notes')
    // The code left unplaced by the layout still renders, in the synthetic trailing section.
    expect(screen.getByRole('button', { name: 'Other information' })).toBeInTheDocument()
  })

  it('falls back to the flat list when the opportunity carries no resolved layout (AC-007)', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel({ attribute_layout: null }))

    renderPanel()

    await waitFor(() => expect(screen.getByRole('textbox', { name: 'Notes' })).toBeInTheDocument())
    expect(screen.queryByRole('heading', { name: 'Qualification' })).not.toBeInTheDocument()
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

    expect(await screen.findByText('activity-log:request-management:1')).toBeInTheDocument()
    expect(activityLogSectionMock).toHaveBeenCalledWith(
      expect.objectContaining({ resource: 'request-management', id: 1 }),
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

  /** Directive 2026-07-27: read-only "Note generali" highlighted in the side column. */
  it('shows the general notes from the read-only context block', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(
      panel({
        context: {
          estimated_value: null,
          expected_close_date: null,
          success_probability: null,
          general_notes: 'Recall the client in September',
        },
      }),
    )

    renderPanel()

    const region = await screen.findByRole('region', { name: 'General notes' })
    expect(region).toHaveTextContent('Recall the client in September')
  })
})
