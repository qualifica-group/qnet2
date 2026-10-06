import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { act, fireEvent, render, renderHook, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { OpportunityForm } from '@/features/opportunities/opportunity-form'
import { useOpportunityLeadSelection } from '@/features/opportunities/use-opportunity-lead-selection'
import type { ResourceMeta } from '@/features/authorization/types'
import {
  FULL_PERMISSIONS,
  ROW,
  SELECT_IDS,
  TEST_PRODUCT_ID,
  applyRow,
  clickSave,
  formTestWrapper,
  openRow,
  queryPencil,
  resolveForSelectLabels,
  saveButtons,
} from '@/features/opportunities/opportunity-form-test-helpers'

/**
 * Amendment rev.1, A-1: the in-form "Lead" select (AC-086/087). Amendment
 * rev.3: the lead's derived function+category is no longer a locked single
 * field — it seeds a `product_lines` ROW instead (AC-102/103), always
 * editable/removable. Split out of `opportunity-form-body.test.tsx` for file
 * size (engineering.md §6): here only the Lead field itself is under test.
 *
 * Spec 0198: the Lead is the create form's one always-open control; every
 * other field is a closed row, and the BR-2 locked ones (anagrafica, fonte)
 * have no pencil. The EDIT form's read-only Lead field (AC-088) went with the
 * edit form: the detail shows the originating lead as a read-only row.
 */

const createOpportunityMock = vi.fn()

vi.mock('@/features/product-lines/product-category-tree-select', async () =>
  await import('@/features/product-lines/product-category-tree-select-stub'))

vi.mock('@/features/product-lines/product-category-root-select', async () => ({
  ProductCategoryRootSelect: (await import('@/features/opportunities/opportunity-form-test-helpers'))
    .ProductCategoryRootSelectDouble,
}))

vi.mock('@/features/opportunities/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/opportunities/api')>(
    '@/features/opportunities/api',
  )
  return { ...actual, createOpportunity: (...args: unknown[]) => createOpportunityMock(...args) }
})

// Closed rows link their related records (`RecordLink`): the open-mode
// preference and the abilities are stubbed rather than dragging an AuthProvider
// in; every ability is granted, so the links render.
vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'modal',
}))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
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

vi.mock('@/components/ui/async-paginated-select', async () => ({
  AsyncPaginatedSelect: (await import('@/features/opportunities/opportunity-form-test-helpers'))
    .AsyncPaginatedSelectDouble,
}))

/** The products-of-interest picker, one button per selectable product. */
vi.mock('@/components/ui/async-paginated-multi-select', async () => ({
  AsyncPaginatedMultiSelect: (await import('@/features/opportunities/opportunity-form-test-helpers'))
    .AsyncPaginatedMultiSelectDouble,
}))

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>('@/features/for-select/api')
  return { ...actual, fetchForSelect: (...args: unknown[]) => fetchForSelectMock(...args) }
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

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  for (const key of Object.keys(SELECT_IDS)) {
    delete SELECT_IDS[key]
  }
  SELECT_IDS.Lead = [TEST_LEAD_ID, TEST_LEAD_ALREADY_LINKED_ID, TEST_LEAD_NO_OPERATOR_ID]
  createOpportunityMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: FULL_PERMISSIONS })

  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockImplementation(async (resource: string, params: { ids?: number[] }) =>
    resolveForSelectLabels(resource, params),
  )

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

function renderCreateForm() {
  render(<OpportunityForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
    wrapper: formTestWrapper(),
  })
}

async function pickLead(leadId: number) {
  await waitFor(() => expect(screen.getByTestId('select-Lead')).toBeInTheDocument())
  fireEvent.click(screen.getByRole('button', { name: `select Lead ${leadId}` }))
}

/** Fills what the original submit flows filled before saving, one closed row at a time. */
function fillSupervisorAndProduct() {
  openRow(ROW.supervisor)
  fireEvent.click(screen.getByRole('button', { name: 'select Supervisor 1' }))
  applyRow()
  openRow(ROW.productsOfInterest)
  fireEvent.click(screen.getByRole('button', { name: `select Products of interest ${TEST_PRODUCT_ID}` }))
  applyRow()
}

describe('OpportunityFormBody — in-form Lead select (AC-086/087)', () => {
  it('applies BR-1 values and BR-2 locks when a lead is picked, and shows the origin banner', async () => {
    renderCreateForm()

    await pickLead(TEST_LEAD_ID)

    // AC-051: the registry's name hydrates from `references.registry`.
    expect(await screen.findByRole('link', { name: /Acme S\.p\.A\./ })).toBeInTheDocument()
    // REQUIREMENT CHANGED (spec 0198 D-4, BR-2): the locked fields are closed
    // rows with NO pencil, no longer a disabled control.
    expect(queryPencil(ROW.registry)).not.toBeInTheDocument()
    expect(await screen.findByText('Web')).toBeInTheDocument()
    expect(queryPencil(ROW.source)).not.toBeInTheDocument()
    // AC-051: referent_id is no longer derived/locked by a picked lead — free
    // and editable, gated only by the registry now being chosen.
    openRow(ROW.referent)
    expect(screen.getByTestId('value-Contact')).toHaveTextContent('')
    expect(screen.getByTestId('disabled-Contact')).toHaveTextContent('false')
    // Spec 0132 (AC-102/103): the derived category seeds a normal,
    // editable/removable product-line row — never a locked field.
    openRow(ROW.productLines)
    expect(screen.getByTestId('value-Product category 1')).toHaveTextContent('50')
    // AC-051: the origin banner also sources its name from the registry now.
    expect(screen.getByRole('status')).toHaveTextContent('Acme S.p.A.')
  })

  /**
   * Directive 2026-07-23 still holds — the lead's Sede operativa is inherited
   * on conversion — but since the 2026-08-05 directive ("oscurare sede
   * operativa e regione") this form renders no row for it, so the
   * inheritance is asserted where it is now observable: in the payload.
   */
  it('inherits the lead operational site into the payload, with no row on screen (directive 2026-07-23 + 2026-08-05)', async () => {
    createOpportunityMock.mockResolvedValue({ id: 1 })

    renderCreateForm()

    await pickLead(TEST_LEAD_ID)
    await screen.findByRole('link', { name: /Acme S\.p\.A\./ })
    expect(queryPencil('Operational site')).not.toBeInTheDocument()
    expect(screen.queryByTestId('select-Operational site')).not.toBeInTheDocument()

    fillSupervisorAndProduct()
    clickSave()

    await waitFor(() => expect(createOpportunityMock).toHaveBeenCalledTimes(1))
    expect(createOpportunityMock.mock.calls[0][0].operational_site_id).toBe(TEST_OPERATIONAL_SITE_ID)
  })

  /**
   * Directive 2026-07-23's other half — clearing the lead clears the
   * inherited Sede — asserted on the hook that owns it: with no row left for
   * it on the form (directive 2026-08-05) and `product_lines` reset to `[]` by
   * the very same clear, a create can no longer submit right after it, so the
   * payload is not an observation point here.
   */
  it('clears the inherited operational site on the hook when the lead selection is cleared', async () => {
    const setValue = vi.fn()
    const { result } = renderHook(
      () => useOpportunityLeadSelection(null, setValue, () => [] as unknown as never),
      { wrapper: formTestWrapper() },
    )

    await act(async () => {
      await result.current.selectLead(null)
    })

    expect(setValue).toHaveBeenCalledWith('operational_site_id', null, { shouldDirty: true })
  })

  it('resets and unlocks the derived fields when the lead selection is cleared', async () => {
    renderCreateForm()

    await pickLead(TEST_LEAD_ID)
    await screen.findByRole('link', { name: /Acme S\.p\.A\./ })
    expect(queryPencil(ROW.registry)).not.toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'clear Lead' }))

    // REQUIREMENT CHANGED (spec 0198): "unlocked" is now "the row has its
    // pencil back", and the referent "disabled" is "no pencil" (it is gated
    // by the registry being unset again, not by the lead lock).
    await waitFor(() => expect(queryPencil(ROW.registry)).toBeInTheDocument())
    expect(screen.queryByRole('link', { name: /Acme S\.p\.A\./ })).not.toBeInTheDocument()
    expect(queryPencil(ROW.source)).toBeInTheDocument()
    expect(queryPencil(ROW.referent)).not.toBeInTheDocument()
    // The derived product-line row is cleared away too (rev.3, "least surprising" whole-field reset).
    openRow(ROW.productLines)
    expect(screen.queryByTestId('value-Product category 1')).not.toBeInTheDocument()
    expect(screen.queryByRole('status')).not.toBeInTheDocument()
  })

  it('sends lead_id, keeps product_lines and omits the other locked fields when submitting from a picked lead', async () => {
    createOpportunityMock.mockResolvedValue({ id: 1 })

    renderCreateForm()

    await pickLead(TEST_LEAD_ID)
    await screen.findByRole('link', { name: /Acme S\.p\.A\./ })
    fillSupervisorAndProduct()

    clickSave()

    await waitFor(() => expect(createOpportunityMock).toHaveBeenCalledTimes(1))
    const payload = createOpportunityMock.mock.calls[0][0]
    expect(payload.lead_id).toBe(TEST_LEAD_ID)
    expect(payload).not.toHaveProperty('registry_id')
    // AC-051: referent_id is no longer among the locked fields — it is sent
    // like any other free field (here still unset by the user).
    expect(payload.referent_id).toBeNull()
    expect(payload).not.toHaveProperty('source_id')
    // Spec 0132: product_lines is NEVER locked — always sent, in full, with
    // only `product_category_id` (the server derives the function).
    expect(payload.product_lines).toEqual([{ product_category_id: 50 }])
    expect(payload.products_of_interest).toEqual([TEST_PRODUCT_ID])
  })

  it('blocks the submit and shows a message when the picked lead already has an opportunity (AC-087)', async () => {
    renderCreateForm()

    await pickLead(TEST_LEAD_ALREADY_LINKED_ID)

    await waitFor(() =>
      expect(screen.getByRole('alert')).toHaveTextContent('This lead already has an opportunity'),
    )
    expect(screen.getByRole('link', { name: 'Go to the opportunity' })).toHaveAttribute(
      'href',
      '/opportunities/777',
    )
    for (const save of saveButtons()) {
      expect(save).toBeDisabled()
    }
    // Never applied: no derived value written, no lock either.
    expect(queryPencil(ROW.registry)).toBeInTheDocument()

    clickSave()
    expect(createOpportunityMock).not.toHaveBeenCalled()
  })
})
