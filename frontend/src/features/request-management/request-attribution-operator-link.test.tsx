import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { RequestWorkPanelScreen } from '@/features/request-management/request-work-panel'
import { OFFER_LINE_FIBRA } from '@/features/request-management/request-work-panel-fixtures'
import type { RequestWorkPanelWithPermissions } from '@/features/request-management/types'
import type { ForSelectItem } from '@/features/for-select/types'
import type { UserForSelectItem } from '@/features/users/for-select-api'

/**
 * Sede <-> Operatore reciprocal filtering/linking in the "Lavora" panel (user
 * directive 2026-07-23): the same three behaviours the Lead form already has
 * (spec 0048 AC-060..062, `lead-form-body-operator-link.test.tsx`), whose
 * mocking approach this file mirrors — `AsyncPaginatedSelect` is stubbed by
 * its accessible trigger label so a pick can be driven without a real
 * dropdown, and the `params` each render received are exposed for assertion.
 * Mounted through the real panel (like `request-callback-section.test.tsx`)
 * because the payload diff lives in `useRequestWorkForm`/
 * `buildRequestWorkPayload`, not in the section.
 *
 * AC-003 (spec 0097): the link now hangs off the TEAM editor's operator slot
 * (`ManagerSlotsField`, position 2) instead of the retired single "Operatore"
 * picker — the field changed, its rules did not. The slot's trigger label is
 * the editor's own default denomination, since this panel resolves no
 * category G.A. labels.
 *
 * AC-011 (spec 0097 rev-2): those two ends are now in two DIFFERENT sections
 * — the Sede in "Attribuzione", the slot in "Team" — and the link is cabled by
 * the panel that owns the form (`useRequestSiteOperatorLink`). Every rule
 * below is unchanged, which is exactly what mounting the real panel proves.
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

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

/** An operator carrying its own employment Sede in `meta` — drives the auto-fill. */
const OPERATOR_WITH_SITE: UserForSelectItem = {
  id: 5,
  label: 'Ada Lovelace',
  meta: { operational_site_id: 66, operational_site_label: 'Depot Z' },
}

/** An operator with no employment Sede — must leave the Sede untouched. */
const OPERATOR_NO_SITE: UserForSelectItem = { id: 6, label: 'Bob Noyce', meta: undefined }

const SITE_A: ForSelectItem = { id: 77, label: 'Warehouse A' }
const SITE_B: ForSelectItem = { id: 88, label: 'Warehouse B' }

/** Default denomination of the operator slot (position 2), with no category label resolved. */
const OPERATOR_SLOT_LABEL = 'Account manager 2'

/** A neighbouring slot, to assert the Sede rules never spill onto the rest of the team. */
const OTHER_SLOT_LABEL = 'Account manager 1'

vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    value,
    onChange,
    onItemChange,
    labels,
    params,
  }: {
    value: number | null
    onChange: (value: number | null) => void
    onItemChange?: (item: ForSelectItem | null) => void
    labels: { triggerLabel: string }
    params?: Record<string, string | number>
  }) => (
    <button
      type="button"
      data-testid={`select-${labels.triggerLabel}`}
      data-params={params ? JSON.stringify(params) : ''}
      onClick={() => {
        if (labels.triggerLabel === 'Operational site') {
          const next = value === SITE_A.id ? SITE_B : SITE_A
          onChange(next.id)
          onItemChange?.(next)
          return
        }
        if (labels.triggerLabel === OPERATOR_SLOT_LABEL) {
          const next = value === OPERATOR_WITH_SITE.id ? OPERATOR_NO_SITE : OPERATOR_WITH_SITE
          onChange(next.id)
          onItemChange?.(next)
          return
        }
        onChange(3)
        onItemChange?.(null)
      }}
    >
      {value ?? ''}
    </button>
  ),
}))

const FULL_PERMISSIONS = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

function panel(overrides: Partial<RequestWorkPanelWithPermissions> = {}): RequestWorkPanelWithPermissions {
  return {
    // Deliberately different from `opportunity_id` (spec 0086 D-9/D-10): not
    // this suite's own concern, but kept apart everywhere so a coincidental
    // pass never hides an inverted wiring elsewhere.
    id: 4001,
    opportunity_id: 8001,
    name: 'Enterprise deal',
    registry: { id: 10, name: 'Acme S.p.A.' },
    referent: { id: 20, name: 'Mario Rossi' },
    commercial: null,
    source_id: 30,
    source: { id: 30, name: 'Web' },
    reporter_id: null,
    reporter: null,
    supervisor_id: null,
    supervisor: null,
    operator_id: null,
    operator: null,
    operational_site_id: null,
    operational_site: null,
    is_transferred: false,
    transferred_from: null,
    status: { source: 'default', distinct_count: 0, entries: [] },
    product_lines: [],
    offer_lines: [OFFER_LINE_FIBRA],
    client_identity: null,
    client_contacts: { owner: null, items: [] },
    client_address: null,
    referent_contacts: { owner: null, items: [] },
    next_callback_at: null,
    attribute_values: {},
    applicable_attributes: [],
    attribute_layout: null,
    quote_workflow_status_id: null,
    quote_workflow_status: null,
    quote_workflow_statuses: [],
    context: { estimated_value: null, expected_close_date: null, success_probability: null },
    permissions: FULL_PERMISSIONS,
    ...overrides,
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

const siteField = () => screen.getByTestId('select-Operational site')
const operatorField = () => screen.getByTestId(`select-${OPERATOR_SLOT_LABEL}`)
const otherSlotField = () => screen.getByTestId(`select-${OTHER_SLOT_LABEL}`)

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchRequestWorkPanelMock.mockReset()
  updateRequestWorkMock.mockReset()
})

describe('Work panel — the Sede scopes the Operatore picker', () => {
  it('sends no filter while the request has no Sede', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())

    renderPanel()

    await waitFor(() => expect(operatorField()).toHaveAttribute('data-params', ''))
    expect(screen.queryByText('Only the operators of the selected site.')).not.toBeInTheDocument()
  })

  /** AC-003: only the operator slot is bound to the Sede — the rest of the team is not. */
  it('leaves every other slot unfiltered even with a Sede set', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(
      panel({ operational_site_id: 77, operational_site: { id: 77, label: 'Warehouse A' } }),
    )

    renderPanel()

    await waitFor(() =>
      expect(operatorField()).toHaveAttribute('data-params', JSON.stringify({ operational_site_id: 77 })),
    )
    expect(otherSlotField()).toHaveAttribute('data-params', '')
  })

  it('passes the persisted Sede as `operational_site_id` on mount', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(
      panel({ operational_site_id: 77, operational_site: { id: 77, label: 'Warehouse A' } }),
    )

    renderPanel()

    await waitFor(() =>
      expect(operatorField()).toHaveAttribute('data-params', JSON.stringify({ operational_site_id: 77 })),
    )
    expect(screen.getByText('Only the operators of the selected site.')).toBeInTheDocument()
  })

  it('drops the scoping hint when the two fields are not visible', async () => {
    const hidden = { visible: false, hidden: true, editable: false, readonly: false, required: false, disabled: true }
    fetchRequestWorkPanelMock.mockResolvedValue(
      panel({
        operational_site_id: 77,
        operational_site: { id: 77, label: 'Warehouse A' },
        permissions: {
          ...FULL_PERMISSIONS,
          fields: { manager_slots: hidden, operational_site_id: hidden },
        },
      }),
    )

    renderPanel()

    await waitFor(() => expect(screen.queryByTestId(`select-${OPERATOR_SLOT_LABEL}`)).not.toBeInTheDocument())
    expect(screen.queryByText('Only the operators of the selected site.')).not.toBeInTheDocument()
  })

  it('re-scopes the picker as soon as a Sede is picked', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())

    renderPanel()
    fireEvent.click(await screen.findByTestId('select-Operational site'))

    await waitFor(() =>
      expect(operatorField()).toHaveAttribute('data-params', JSON.stringify({ operational_site_id: SITE_A.id })),
    )
  })
})

describe('Work panel — Operatore auto-fills the Sede', () => {
  it('hydrates the Sede from the picked operator meta, with no extra fetch', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())

    renderPanel()
    fireEvent.click(await screen.findByTestId(`select-${OPERATOR_SLOT_LABEL}`))

    await waitFor(() => expect(siteField()).toHaveTextContent(String(OPERATOR_WITH_SITE.meta?.operational_site_id)))
  })

  it('leaves the Sede untouched for an operator with no Sede', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(
      panel({ operational_site_id: 77, operational_site: { id: 77, label: 'Warehouse A' } }),
    )

    renderPanel()
    // The stub picks OPERATOR_WITH_SITE first, then OPERATOR_NO_SITE.
    fireEvent.click(await screen.findByTestId(`select-${OPERATOR_SLOT_LABEL}`))
    await waitFor(() => expect(siteField()).toHaveTextContent('66'))
    fireEvent.click(operatorField())

    await waitFor(() => expect(operatorField()).toHaveTextContent(String(OPERATOR_NO_SITE.id)))
    expect(siteField()).toHaveTextContent('66')
  })

  it('does NOT clear the operator it just auto-filled the Sede from', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())

    renderPanel()
    fireEvent.click(await screen.findByTestId(`select-${OPERATOR_SLOT_LABEL}`))

    await waitFor(() => expect(operatorField()).toHaveTextContent(String(OPERATOR_WITH_SITE.id)))
  })
})

describe('Work panel — changing the Sede clears the Operatore', () => {
  it('clears an operator belonging to the previous Sede', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())

    renderPanel()
    fireEvent.click(await screen.findByTestId(`select-${OPERATOR_SLOT_LABEL}`)) // operator 5, Sede 66
    await waitFor(() => expect(siteField()).toHaveTextContent('66'))

    fireEvent.click(siteField()) // a REAL Sede pick, different from 66

    // The stub renders `value ?? ''`, so an emptied operator has no text node
    // at all — asserted positively, since `toHaveTextContent('')` passes on
    // any content.
    await waitFor(() => expect(operatorField().textContent).toBe(''))
  })

  /** AC-003: the Sede change empties the operator slot ALONE — G.A. 1 survives it. */
  it('submits the emptied operator slot, keeping the rest of the team', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(
      panel({
        operator_id: 5,
        operator: { id: 5, name: 'Ada Lovelace' },
        managers: [
          { id: 7, name: 'Grace Hopper', position: 1 },
          { id: 5, name: 'Ada Lovelace', position: 2 },
        ],
        operational_site_id: 77,
        operational_site: { id: 77, label: 'Warehouse A' },
      }),
    )
    updateRequestWorkMock.mockResolvedValue(panel())

    renderPanel()
    fireEvent.click(await screen.findByTestId('select-Operational site')) // 77 -> SITE_B
    fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateRequestWorkMock).toHaveBeenCalledTimes(1))
    expect(updateRequestWorkMock.mock.calls[0][1]).toEqual({
      operational_site_id: SITE_B.id,
      manager_slots: [7, null, null, null],
    })
  })
})
