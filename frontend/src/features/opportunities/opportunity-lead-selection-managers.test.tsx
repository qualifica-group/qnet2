import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { OpportunityForm } from '@/features/opportunities/opportunity-form'
import type { ResourceMeta } from '@/features/authorization/types'

/**
 * Split out of `opportunity-lead-selection.test.tsx` for file size
 * (engineering.md §6): the in-form "Lead" select's own effect on the
 * `manager_slots` field — appending the lead's Operator as a "Gestore
 * Account" slot (user directive 2026-07-21) — is a distinct enough concern
 * to stand on its own. Every other in-form Lead select behavior (BR-1
 * values/locks, the origin banner, submit) lives in the sibling file.
 */

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
/** A lead with no Operator — `manager_slots`/`manager_refs` both empty. */
const TEST_LEAD_NO_OPERATOR_ID = 902
const TEST_OPPORTUNITY_STATUS_ID = 5
/** Directive 2026-07-21: the Operator derived onto `TEST_LEAD_ID`, seeding the first Gestore Account slot. */
const TEST_OPERATOR_ID = 300
/** Directive 2026-07-23: the Sede operativa inherited from `TEST_LEAD_ID` on conversion. */
const TEST_OPERATIONAL_SITE_ID = 400

/** Fixed selection ids exposed per field (by accessible trigger label), mirrors `opportunity-form-body.test.tsx`. */
const SELECT_IDS: Record<string, number[]> = {
  Lead: [TEST_LEAD_ID, TEST_LEAD_NO_OPERATOR_ID],
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

vi.mock('@/components/ui/async-paginated-multi-select', () => ({
  AsyncPaginatedMultiSelect: ({ value, labels }: { value: number[]; labels: { triggerLabel: string } }) => (
    <div data-testid={`multi-${labels.triggerLabel}`}>
      <span data-testid={`value-multi-${labels.triggerLabel}`}>{value.join(',')}</span>
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

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: FULL_PERMISSIONS })

  fetchSystemStatusIdMock.mockReset()
  fetchSystemStatusIdMock.mockResolvedValue(TEST_OPPORTUNITY_STATUS_ID)

  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockResolvedValue(EMPTY_PAGE)

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

describe('OpportunityFormBody — in-form Lead select, Gestori Account (directive 2026-07-21/22)', () => {
  it('appends the lead Operator as the second Gestore Account slot on selection, G.A. 1 left empty, still editable, Supervisor left empty', async () => {
    render(<OpportunityForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Lead')).toBeInTheDocument())
    // User directive 2026-07-29: the four G.A. slots render from the start,
    // all empty (they used to be materialized only by "Add").
    expect(screen.getByTestId('value-Account manager 1')).toHaveTextContent('')
    expect(screen.getByTestId('value-Account manager 4')).toHaveTextContent('')

    screen.getByRole('button', { name: `select Lead ${TEST_LEAD_ID}` }).click()

    await waitFor(() =>
      expect(screen.getByTestId('value-Account manager 2')).toHaveTextContent(String(TEST_OPERATOR_ID)),
    )
    // G.A. 1 is materialized but empty.
    expect(screen.getByTestId('value-Account manager 1')).toHaveTextContent('')
    // Precompiled, never locked: the user can still change it (unlike Registry/Source above).
    expect(screen.getByTestId('disabled-Account manager 2')).toHaveTextContent('false')
    // The trigger label is hydrated too — `setValue` alone writes the id, not the name.
    expect(screen.getByTestId('label-Account manager 2')).toHaveTextContent('Giulia Bianchi')
    // The Supervisor is no longer prefilled from the lead.
    expect(screen.getByTestId('value-Supervisor')).toHaveTextContent('')
  })

  it('leaves the Gestori account empty when the picked lead has no Operator', async () => {
    render(<OpportunityForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Lead')).toBeInTheDocument())
    screen.getByRole('button', { name: `select Lead ${TEST_LEAD_NO_OPERATOR_ID}` }).click()

    await waitFor(() => expect(screen.getByTestId('value-Registry')).toHaveTextContent(String(TEST_REGISTRY_ID)))
    // No Operator -> the default slots stay empty, Supervisor too.
    expect(screen.getByTestId('value-Account manager 1')).toHaveTextContent('')
    expect(screen.getByTestId('value-Account manager 2')).toHaveTextContent('')
    expect(screen.getByTestId('value-Supervisor')).toHaveTextContent('')
  })

  it('appends the Operator alongside a manager the user already picked, never overwriting it', async () => {
    render(<OpportunityForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Lead')).toBeInTheDocument())
    // Manually add a first G.A. slot and pick a user (mock id 1) into it.
    fireEvent.click(screen.getByRole('button', { name: 'Add account manager' }))
    fireEvent.click(screen.getByRole('button', { name: 'select Account manager 1 1' }))
    expect(screen.getByTestId('value-Account manager 1')).toHaveTextContent('1')

    screen.getByRole('button', { name: `select Lead ${TEST_LEAD_ID}` }).click()

    await waitFor(() => expect(screen.getByTestId('value-Registry')).toHaveTextContent(String(TEST_REGISTRY_ID)))
    // The user's own manager is kept in slot 1; the Operator is appended as slot 2.
    expect(screen.getByTestId('value-Account manager 1')).toHaveTextContent('1')
    expect(screen.getByTestId('value-Account manager 2')).toHaveTextContent(String(TEST_OPERATOR_ID))
  })
})
