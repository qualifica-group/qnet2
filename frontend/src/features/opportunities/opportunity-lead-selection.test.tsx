import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { act, fireEvent, render, renderHook, screen, waitFor, within } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { OpportunityForm } from '@/features/opportunities/opportunity-form'
import { useOpportunityLeadSelection } from '@/features/opportunities/use-opportunity-lead-selection'
import type { OpportunityDetailWithPermissions } from '@/features/opportunities/types'
import type { ResourceMeta } from '@/features/authorization/types'

/**
 * Amendment rev.1, A-1: the in-form "Lead" select (AC-086/087) and the EDIT
 * form's read-only Lead field (AC-088). Amendment rev.3: the lead's derived
 * function+category is no longer a locked single field — it seeds a
 * `product_lines` ROW instead (AC-102/103), always editable/removable. Split
 * out of `opportunity-form-body.test.tsx` for file size (engineering.md §6) —
 * every other field's own behavior (BR-4 scoping, field permissions,
 * deep-link create-from-lead) is covered there; here only the Lead field
 * itself is under test.
 *
 * User directive 2026-07-21: a lead selection now appends the lead's Operator
 * as the first "Gestore Account" slot (not the Supervisor, which stays empty),
 * only when it isn't already among the slots. The fixtures below carry
 * `manager_slots`/`manager_refs` accordingly.
 */

const createOpportunityMock = vi.fn()

/**
 * The row's category picker reads the category TREE (user directive
 * 2026-08-03) and mounts its own quick-create affordance; this suite is about
 * the surrounding form, so it stands in for the picker with the shared double.
 */
vi.mock('@/features/product-lines/product-category-tree-select', async () =>
  await import('@/features/product-lines/product-category-tree-select-stub'))

vi.mock('@/features/opportunities/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/opportunities/api')>(
    '@/features/opportunities/api',
  )
  return {
    ...actual,
    createOpportunity: (...args: unknown[]) => createOpportunityMock(...args),
  }
})

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const FULL_PERMISSIONS = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

/** Spec 0043 D-3: the create form preselects this resolved "Nuova" status id. */
const fetchSystemStatusIdMock = vi.fn<() => Promise<number | null>>()
vi.mock('@/features/status-reorder/api', () => ({
  fetchSystemStatusId: () => fetchSystemStatusIdMock(),
}))

const TEST_REGISTRY_ID = 10
const TEST_LEAD_ID = 900
const TEST_LEAD_ALREADY_LINKED_ID = 901
/** A lead with no Operator — `manager_slots`/`manager_refs` both empty. */
const TEST_LEAD_NO_OPERATOR_ID = 902
/** Directive 2026-07-21: the Operator derived onto `TEST_LEAD_ID`, seeding the first Gestore Account slot. */
const TEST_OPERATOR_ID = 300
/** Directive 2026-07-23: the Sede operativa inherited from `TEST_LEAD_ID` on conversion. */
const TEST_OPERATIONAL_SITE_ID = 400

/** Fixed selection ids exposed per field (by accessible trigger label), mirrors `opportunity-form-body.test.tsx`. */
const SELECT_IDS: Record<string, number[]> = {
  Lead: [TEST_LEAD_ID, TEST_LEAD_ALREADY_LINKED_ID, TEST_LEAD_NO_OPERATOR_ID],
}

vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    value,
    onChange,
    disabled,
    selectedItem,
    labels,
  }: {
    value: number | null
    onChange: (value: number | null) => void
    disabled?: boolean
    selectedItem?: { id: number; label: string } | null
    labels: { triggerLabel: string }
  }) => (
    <div data-testid={`select-${labels.triggerLabel}`}>
      <span data-testid={`value-${labels.triggerLabel}`}>{value ?? ''}</span>
      <span data-testid={`disabled-${labels.triggerLabel}`}>{String(Boolean(disabled))}</span>
      <span data-testid={`label-${labels.triggerLabel}`}>{selectedItem?.label ?? ''}</span>
      {(SELECT_IDS[labels.triggerLabel] ?? [1]).map((id) => (
        <button key={id} type="button" onClick={() => onChange(id)}>
          {`select ${labels.triggerLabel} ${id}`}
        </button>
      ))}
      <button type="button" onClick={() => onChange(null)}>{`clear ${labels.triggerLabel}`}</button>
    </div>
  ),
}))

/**
 * The products-of-interest picker (mandatory since the user directive
 * 2026-07-23, so no create can submit without it): stubbed like the single
 * selects above, one button per selectable product.
 */
const TEST_PRODUCT_ID = 700

vi.mock('@/components/ui/async-paginated-multi-select', () => ({
  AsyncPaginatedMultiSelect: ({
    value,
    onChange,
    labels,
  }: {
    value: number[]
    onChange: (value: number[]) => void
    labels: { triggerLabel: string }
  }) => (
    <div data-testid={`multi-${labels.triggerLabel}`}>
      <span data-testid={`value-multi-${labels.triggerLabel}`}>{value.join(',')}</span>
      <button type="button" onClick={() => onChange([...value, TEST_PRODUCT_ID])}>
        {`select ${labels.triggerLabel} ${TEST_PRODUCT_ID}`}
      </button>
    </div>
  ),
}))

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>(
    '@/features/for-select/api',
  )
  return {
    ...actual,
    fetchForSelect: (...args: unknown[]) => fetchForSelectMock(...args),
  }
})

/**
 * Controls the in-form "Lead" select's one-shot defaults fetch (spec 0040
 * A-1): `useOpportunityLeadSelection` calls this directly, sharing the exact
 * same underlying endpoint as the `?lead_id=N` deep-link.
 */
const fetchOpportunityDefaultsOnceMock = vi.fn()
vi.mock('@/features/opportunities/opportunity-defaults-api', async () => {
  const actual = await vi.importActual<typeof import('@/features/opportunities/opportunity-defaults-api')>(
    '@/features/opportunities/opportunity-defaults-api',
  )
  return {
    ...actual,
    fetchOpportunityDefaultsOnce: (...args: unknown[]) => fetchOpportunityDefaultsOnceMock(...args),
  }
})

const EMPTY_PAGE = { items: [], pagination: { offset: 0, limit: 25, total: 0 }, export_link: null }

/** The "Go to the opportunity" CTA (AC-087) renders a `<Link>`: needs a router context. */
/** The products-of-interest picker opens the shared confirm dialog, so its provider is required. */
function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>
        <MemoryRouter>{children}</MemoryRouter>
      </ConfirmDialogProvider>
    </QueryClientProvider>
  )
}

function editOpportunity(
  overrides: Partial<OpportunityDetailWithPermissions> = {},
): OpportunityDetailWithPermissions {
  return {
    id: 1,
    name: 'Enterprise deal',
    registry_id: TEST_REGISTRY_ID,
    registry: { id: TEST_REGISTRY_ID, name: 'Acme S.p.A.' },
    referent_id: 10,
    referent: { id: 10, name: 'Mario Rossi' },
    commercial_id: null,
    commercial: null,
    reporter_id: null,
    reporter: null,
    supervisor_id: null,
    supervisor: null,
    source_id: 20,
    source: { id: 20, name: 'Web' },
    status: { source: 'workflow', distinct_count: 0, entries: [] },
    product_lines: [
      {
        id: 500,
        business_function: { id: 40, name: 'Sales' },
        product_category: { id: 50, name: 'Consulting' },
      },
    ],
    products_of_interest: [{ id: 700, name: 'Fibra 1000', product_category: { id: 50, name: 'Consulting' } }],
    lead_id: 900,
    lead: { id: 900, label: 'Mario Rossi' },
    managers: [],
    start_date: null,
    estimated_value: null,
    expected_close_date: null,
    success_probability: null,
    locked_fields: ['referent_id', 'source_id', 'registry_id'],
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    permissions: FULL_PERMISSIONS,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createOpportunityMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: FULL_PERMISSIONS })

  fetchSystemStatusIdMock.mockReset()

  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockResolvedValue(EMPTY_PAGE)

  fetchOpportunityDefaultsOnceMock.mockReset()
  fetchOpportunityDefaultsOnceMock.mockImplementation(async (_client: unknown, leadId: number) => {
    if (leadId === TEST_LEAD_ALREADY_LINKED_ID) {
      return {
        lead_id: leadId,
        existing_opportunity_id: 777,
        values: { referent_id: null, source_id: null, registry_id: null, operational_site_id: null },
        references: { source: null, registry: null, operational_site: null },
        locked_fields: [],
        product_lines: [],
        manager_slots: [],
        manager_refs: [],
      }
    }
    return {
      lead_id: leadId,
      existing_opportunity_id: null,
      values: {
        // spec 0041 D-3/AC-050: no longer a derived field — the resolver never writes it.
        referent_id: null,
        source_id: 20,
        registry_id: TEST_REGISTRY_ID,
        // Directive 2026-07-23: inherited on conversion, never locked.
        operational_site_id: TEST_OPERATIONAL_SITE_ID,
      },
      references: {
        source: { id: 20, name: 'Web' },
        registry: { id: TEST_REGISTRY_ID, name: 'Acme S.p.A.' },
        operational_site: { id: TEST_OPERATIONAL_SITE_ID, label: 'Via Roma 1 - Milano' },
      },
      locked_fields: ['source_id', 'registry_id'],
      // Amendment rev.3 (AC-102/103): editable/removable seed row, never locked.
      product_lines: [
        {
          id: 900,
          business_function: { id: 40, name: 'Sales' },
          product_category: { id: 50, name: 'Consulting' },
        },
      ],
      // Directive 2026-07-22: the lead's Operator seeds the SECOND Gestore
      // Account slot (G.A. 1 empty), absent for TEST_LEAD_NO_OPERATOR_ID.
      // Never locked.
      manager_slots: leadId === TEST_LEAD_NO_OPERATOR_ID ? [] : [null, TEST_OPERATOR_ID],
      manager_refs:
        leadId === TEST_LEAD_NO_OPERATOR_ID ? [] : [{ id: TEST_OPERATOR_ID, name: 'Giulia Bianchi' }],
    }
  })
})

describe('OpportunityFormBody — in-form Lead select (AC-086/087)', () => {
  it('applies BR-1 values and BR-2 locks when a lead is picked, and shows the origin banner', async () => {
    render(<OpportunityForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Lead')).toBeInTheDocument())
    screen.getByRole('button', { name: `select Lead ${TEST_LEAD_ID}` }).click()

    await waitFor(() => expect(screen.getByTestId('value-Registry')).toHaveTextContent(String(TEST_REGISTRY_ID)))
    expect(screen.getByTestId('disabled-Registry')).toHaveTextContent('true')
    // AC-051: the registry's label hydrates the trigger from `references.registry`.
    expect(screen.getByTestId('label-Registry')).toHaveTextContent('Acme S.p.A.')
    // AC-051: referent_id is no longer derived/locked by a picked lead — free
    // and editable, gated only by the registry now being chosen.
    expect(screen.getByTestId('value-Contact')).toHaveTextContent('')
    expect(screen.getByTestId('disabled-Contact')).toHaveTextContent('false')
    expect(screen.getByTestId('value-Source')).toHaveTextContent('20')
    expect(screen.getByTestId('disabled-Source')).toHaveTextContent('true')
    // Amendment rev.3 (AC-102/103): the derived function+category is a
    // normal, editable/removable product-line row — never a locked field.
    expect(screen.getByText('Sales')).toBeInTheDocument()
    // The category picker is the tree select's double here, so the row is
    // asserted by the id it carries; the label now comes from the tree that
    // component reads (covered against the real one in the work-panel suite).
    expect(screen.getByTestId('value-Product category 1')).toHaveTextContent('50')
    // AC-051: the origin banner also sources its name from the registry now.
    expect(screen.getByRole('status')).toHaveTextContent('Acme S.p.A.')
  })

  /**
   * Directive 2026-07-23 still holds — the lead's Sede operativa is inherited
   * on conversion — but since the 2026-08-05 directive ("oscurare sede
   * operativa e regione") this form renders no picker for it, so the
   * inheritance is asserted where it is now observable: in the payload.
   */
  it('inherits the lead operational site into the payload, with no picker on screen (directive 2026-07-23 + 2026-08-05)', async () => {
    createOpportunityMock.mockResolvedValue({ id: 1 })

    render(<OpportunityForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Lead')).toBeInTheDocument())
    screen.getByRole('button', { name: `select Lead ${TEST_LEAD_ID}` }).click()
    await waitFor(() => expect(screen.getByTestId('disabled-Registry')).toHaveTextContent('true'))
    expect(screen.queryByTestId('select-Operational site')).not.toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'select Supervisor 1' }))
    fireEvent.click(screen.getByRole('button', { name: `select Products of interest ${TEST_PRODUCT_ID}` }))
    fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createOpportunityMock).toHaveBeenCalledTimes(1))
    expect(createOpportunityMock.mock.calls[0][0].operational_site_id).toBe(TEST_OPERATIONAL_SITE_ID)
  })

  /**
   * Directive 2026-07-23's other half — clearing the lead clears the
   * inherited Sede — asserted on the hook that owns it: with no picker left on
   * the form (directive 2026-08-05) and `product_lines` reset to `[]` by the
   * very same clear, a create can no longer submit right after it, so the
   * payload is not an observation point here.
   */
  it('clears the inherited operational site on the hook when the lead selection is cleared', async () => {
    const setValue = vi.fn()
    const { result } = renderHook(
      () => useOpportunityLeadSelection(null, setValue, () => [] as unknown as never),
      { wrapper: wrapper() },
    )

    await act(async () => {
      await result.current.selectLead(null)
    })

    expect(setValue).toHaveBeenCalledWith('operational_site_id', null, { shouldDirty: true })
  })

  it('resets and unlocks the derived fields when the lead selection is cleared', async () => {
    render(<OpportunityForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Lead')).toBeInTheDocument())
    screen.getByRole('button', { name: `select Lead ${TEST_LEAD_ID}` }).click()
    await waitFor(() => expect(screen.getByTestId('disabled-Registry')).toHaveTextContent('true'))

    screen.getByRole('button', { name: 'clear Lead' }).click()

    await waitFor(() => expect(screen.getByTestId('disabled-Registry')).toHaveTextContent('false'))
    expect(screen.getByTestId('value-Registry')).toHaveTextContent('')
    // Referent is still gated by registry being unset again, not by the lead lock.
    expect(screen.getByTestId('disabled-Contact')).toHaveTextContent('true')
    expect(screen.getByTestId('value-Contact')).toHaveTextContent('')
    // The derived product-line row is cleared away too (rev.3, "least surprising" whole-field reset).
    expect(screen.queryByText('Sales')).not.toBeInTheDocument()
    expect(screen.queryByRole('status')).not.toBeInTheDocument()
  })

  it('sends lead_id, keeps product_lines and omits the other locked fields when submitting from a picked lead', async () => {
    createOpportunityMock.mockResolvedValue({ id: 1 })

    render(<OpportunityForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Lead')).toBeInTheDocument())
    screen.getByRole('button', { name: `select Lead ${TEST_LEAD_ID}` }).click()
    await waitFor(() => expect(screen.getByTestId('disabled-Registry')).toHaveTextContent('true'))
    fireEvent.click(screen.getByRole('button', { name: 'select Supervisor 1' }))
    // Mandatory since the user directive 2026-07-23: without a product the
    // form would not submit at all.
    fireEvent.click(screen.getByRole('button', { name: `select Products of interest ${TEST_PRODUCT_ID}` }))

    fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createOpportunityMock).toHaveBeenCalledTimes(1))
    const payload = createOpportunityMock.mock.calls[0][0]
    expect(payload.lead_id).toBe(TEST_LEAD_ID)
    expect(payload).not.toHaveProperty('registry_id')
    // AC-051: referent_id is no longer among the locked fields — it is sent
    // like any other free field (here still unset by the user).
    expect(payload.referent_id).toBeNull()
    expect(payload).not.toHaveProperty('source_id')
    // Amendment rev.3: product_lines is NEVER locked — always sent, in full.
    expect(payload.product_lines).toEqual([{ business_function_id: 40, product_category_id: 50 }])
    expect(payload.products_of_interest).toEqual([TEST_PRODUCT_ID])
  })

  it('blocks the submit and shows a message when the picked lead already has an opportunity (AC-087)', async () => {
    render(<OpportunityForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Lead')).toBeInTheDocument())
    screen.getByRole('button', { name: `select Lead ${TEST_LEAD_ALREADY_LINKED_ID}` }).click()

    await waitFor(() =>
      expect(screen.getByRole('alert')).toHaveTextContent('This lead already has an opportunity'),
    )
    expect(screen.getByRole('link', { name: 'Go to the opportunity' })).toHaveAttribute(
      'href',
      '/opportunities/777',
    )
    expect(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' })).toBeDisabled()
    // Never applied: no derived value written, no lock either.
    expect(screen.getByTestId('disabled-Registry')).toHaveTextContent('false')

    fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))
    expect(createOpportunityMock).not.toHaveBeenCalled()
  })
})

describe('OpportunityFormBody — edit mode Lead field (AC-088)', () => {
  it('shows the linked lead read-only, never as an editable select', async () => {
    render(
      <OpportunityForm mode={{ type: 'edit', opportunity: editOpportunity() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    const leadField = await screen.findByDisplayValue('Mario Rossi')
    expect(leadField).toBeDisabled()
    expect(screen.queryByTestId('select-Lead')).not.toBeInTheDocument()
  })

  it('renders the loaded opportunity for an opportunity with no linked lead', async () => {
    render(
      <OpportunityForm
        mode={{ type: 'edit', opportunity: editOpportunity({ lead_id: null, lead: null }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    await screen.findByTestId('select-Business function 1')
    expect(screen.queryByText('Lead')).not.toBeInTheDocument()
    expect(screen.queryByTestId('select-Lead')).not.toBeInTheDocument()
    // The edit form's loaded product line still renders, hydrated.
    expect(screen.getByTestId('value-Business function 1')).toHaveTextContent('40')
    expect(screen.getByTestId('value-Product category 1')).toHaveTextContent('50')
  })
})
