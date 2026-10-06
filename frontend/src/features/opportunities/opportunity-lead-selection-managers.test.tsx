import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { OpportunityForm } from '@/features/opportunities/opportunity-form'
import type { ResourceMeta } from '@/features/authorization/types'
import {
  FULL_PERMISSIONS,
  ROW,
  SELECT_IDS,
  applyRow,
  formTestWrapper,
  openRow,
  resolveForSelectLabels,
} from '@/features/opportunities/opportunity-form-test-helpers'

/**
 * Split out of `opportunity-lead-selection.test.tsx` for file size
 * (engineering.md §6): the in-form "Lead" select's own effect on the
 * `manager_slots` field — appending the lead's Operator as a "Gestore
 * Account" slot (user directive 2026-07-21) — is a distinct enough concern
 * to stand on its own. Every other in-form Lead select behavior (BR-1
 * values/locks, the origin banner, submit) lives in the sibling file.
 *
 * Spec 0198: the G.A. slots are ONE closed row of the create form ("Account
 * managers"): it shows the filled slots with their "G.A. n" denomination, and
 * its pencil opens the slot editor.
 */

vi.mock('@/features/product-lines/product-category-tree-select', async () =>
  await import('@/features/product-lines/product-category-tree-select-stub'))

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
  SELECT_IDS.Lead = [TEST_LEAD_ID, TEST_LEAD_NO_OPERATOR_ID]
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: FULL_PERMISSIONS })

  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockImplementation(async (resource: string, params: { ids?: number[] }) =>
    resolveForSelectLabels(resource, params),
  )

  fetchOpportunityDefaultsOnceMock.mockReset()
  fetchOpportunityDefaultsOnceMock.mockImplementation(async (_client: unknown, leadId: number) => ({
    lead_id: leadId,
    existing_opportunity_id: null,
    values: {
      referent_id: null,
      source_id: 20,
      registry_id: TEST_REGISTRY_ID,
      operational_site_id: TEST_OPERATIONAL_SITE_ID,
    },
    references: {
      source: { id: 20, name: 'Web' },
      registry: { id: TEST_REGISTRY_ID, name: 'Acme S.p.A.' },
      operational_site: { id: TEST_OPERATIONAL_SITE_ID, label: 'Via Roma 1 - Milano' },
    },
    locked_fields: ['source_id', 'registry_id'],
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
  }))
})

async function renderCreateForm() {
  render(<OpportunityForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
    wrapper: formTestWrapper(),
  })
  await waitFor(() => expect(screen.getByTestId('select-Lead')).toBeInTheDocument())
}

describe('OpportunityFormBody — in-form Lead select, Gestori Account (directive 2026-07-21/22)', () => {
  it('appends the lead Operator as the second Gestore Account slot on selection, G.A. 1 left empty, still editable, Supervisor left empty', async () => {
    await renderCreateForm()
    // User directive 2026-07-29: the four G.A. slots render from the start,
    // all empty (they used to be materialized only by "Add").
    openRow(ROW.managers)
    expect(screen.getByTestId('value-Account manager 1')).toHaveTextContent('')
    expect(screen.getByTestId('value-Account manager 4')).toHaveTextContent('')
    applyRow()

    fireEvent.click(screen.getByRole('button', { name: `select Lead ${TEST_LEAD_ID}` }))

    // The closed row names the Operator, under his "G.A. 2" denomination.
    expect(await screen.findByText('Giulia Bianchi')).toBeInTheDocument()
    openRow(ROW.managers)
    expect(screen.getByTestId('value-Account manager 2')).toHaveTextContent(String(TEST_OPERATOR_ID))
    // G.A. 1 is materialized but empty.
    expect(screen.getByTestId('value-Account manager 1')).toHaveTextContent('')
    // Precompiled, never locked: the user can still change it (unlike Registry/Source).
    expect(screen.getByTestId('disabled-Account manager 2')).toHaveTextContent('false')
    // The trigger label is hydrated too — `setValue` alone writes the id, not the name.
    expect(screen.getByTestId('label-Account manager 2')).toHaveTextContent('Giulia Bianchi')
    applyRow()
    // The Supervisor is no longer prefilled from the lead.
    openRow(ROW.supervisor)
    expect(screen.getByTestId('value-Supervisor')).toHaveTextContent('')
  })

  it('leaves the Gestori account empty when the picked lead has no Operator', async () => {
    await renderCreateForm()
    fireEvent.click(screen.getByRole('button', { name: `select Lead ${TEST_LEAD_NO_OPERATOR_ID}` }))

    // The anagrafica the lead hands down is named on its closed row once applied.
    expect(await screen.findByRole('link', { name: /Acme S\.p\.A\./ })).toBeInTheDocument()
    // No Operator -> the default slots stay empty, Supervisor too.
    openRow(ROW.managers)
    expect(screen.getByTestId('value-Account manager 1')).toHaveTextContent('')
    expect(screen.getByTestId('value-Account manager 2')).toHaveTextContent('')
    applyRow()
    openRow(ROW.supervisor)
    expect(screen.getByTestId('value-Supervisor')).toHaveTextContent('')
  })

  it('appends the Operator alongside a manager the user already picked, never overwriting it', async () => {
    await renderCreateForm()
    // Manually add a first G.A. slot and pick a user (mock id 1) into it.
    openRow(ROW.managers)
    fireEvent.click(screen.getByRole('button', { name: 'Add account manager' }))
    fireEvent.click(screen.getByRole('button', { name: 'select Account manager 1 1' }))
    expect(screen.getByTestId('value-Account manager 1')).toHaveTextContent('1')
    applyRow()

    fireEvent.click(screen.getByRole('button', { name: `select Lead ${TEST_LEAD_ID}` }))

    expect(await screen.findByRole('link', { name: /Acme S\.p\.A\./ })).toBeInTheDocument()
    // The user's own manager is kept in slot 1; the Operator is appended as slot 2.
    openRow(ROW.managers)
    expect(screen.getByTestId('value-Account manager 1')).toHaveTextContent('1')
    expect(screen.getByTestId('value-Account manager 2')).toHaveTextContent(String(TEST_OPERATOR_ID))
  })
})
