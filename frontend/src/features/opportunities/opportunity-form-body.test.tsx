import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { OpportunityForm } from '@/features/opportunities/opportunity-form'
import type { ResourceMeta } from '@/features/authorization/types'
import type { OpportunityDetailWithPermissions } from '@/features/opportunities/types'

/**
 * AC-071 (every field of the contract renders), AC-072 (referent disabled
 * without a registry, scoped by `registry_id` once chosen and reset on registry
 * change; commercial/reporter are free and, per user directive 2026-07-17, are
 * never auto-filled from the anagrafica), AC-074 (field permissions: hidden vs
 * disabled). Spec 0057 (D-5): the name is no longer a form input.
 *
 * The `?lead_id=N` deep-link create-from-lead mode (AC-075) and the in-form
 * "Lead" select (AC-086/087/088, spec 0044 supervisor prefill AC-025/034) are
 * split into their own files for size (engineering.md §6): see
 * `opportunity-form-from-lead.test.tsx` and `opportunity-lead-selection.test.tsx`.
 */

const createOpportunityMock = vi.fn()
const updateOpportunityMock = vi.fn()

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
    updateOpportunity: (...args: unknown[]) => updateOpportunityMock(...args),
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

const TEST_REGISTRY_WITH_DEFAULTS = 10
const TEST_REGISTRY_WITHOUT_DEFAULTS = 20
const TEST_BUSINESS_FUNCTION = 40
const TEST_PRODUCT_CATEGORY = 500

function editOpportunity(): OpportunityDetailWithPermissions {
  return {
    id: 1,
    name: 'Enterprise deal',
    registry_id: TEST_REGISTRY_WITH_DEFAULTS,
    registry: { id: TEST_REGISTRY_WITH_DEFAULTS, name: 'Acme S.p.A.' },
    referent_id: null,
    referent: null,
    commercial_id: null,
    commercial: null,
    reporter_id: null,
    reporter: null,
    supervisor_id: null,
    supervisor: null,
    source_id: null,
    source: null,
    operational_site_id: null,
    operational_site: null,
    state_id: null,
    state: null,
    status: { source: 'workflow', distinct_count: 0, entries: [] },
    opportunity_workflow_status_id: 100,
    workflow_status: { id: 100, name: 'Open', color: 'blue', system_key: 'open', group: 'open', description: null, requires_note: false },
    workflow_statuses: [
      { id: 100, name: 'Open', color: 'blue', system_key: 'open', group: 'open', description: null, requires_note: false },
      { id: 101, name: 'In progress', color: 'amber', system_key: null, group: 'open', description: null, requires_note: false },
      { id: 102, name: 'Closed', color: 'green', system_key: 'closed_won', group: 'closed_won', description: null, requires_note: false },
    ],
    product_lines: [
      {
        id: 1,
        business_function: { id: TEST_BUSINESS_FUNCTION, name: 'Sales' },
        product_category: { id: TEST_PRODUCT_CATEGORY, name: 'Consulting' },
      },
    ],
    lead_id: null,
    lead: null,
    managers: [],
    start_date: null,
    expected_close_date: null,
    estimated_value: null,
    success_probability: null,
    locked_fields: [],
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    permissions: FULL_PERMISSIONS,
  }
}

function labelFor(text: string): HTMLElement {
  return screen.getByText(
    (_, element) => element?.tagName === 'LABEL' && element.textContent?.startsWith(text) === true,
  )
}

/** Fixed selection ids exposed per field (by accessible trigger label), so BR-4 side effects are exercisable without a real dropdown. */
const SELECT_IDS: Record<string, number[]> = {
  Registry: [TEST_REGISTRY_WITH_DEFAULTS, TEST_REGISTRY_WITHOUT_DEFAULTS],
  'Business function 1': [TEST_BUSINESS_FUNCTION],
  'Product category 1': [TEST_PRODUCT_CATEGORY],
}

/**
 * Stubs every single-select field, keyed by its accessible trigger label
 * (mirrors `campaign-project-link.test.tsx`): exposes the current value,
 * disabled state and the `params` this instance received (BR-4 scoping), plus
 * a "select" affordance per fixed id so onChange side effects are exercisable.
 */
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    value,
    onChange,
    disabled,
    params,
    labels,
  }: {
    value: number | null
    onChange: (value: number | null) => void
    disabled?: boolean
    params?: Record<string, string | number>
    labels: { triggerLabel: string }
  }) => (
    <div data-testid={`select-${labels.triggerLabel}`}>
      <span data-testid={`value-${labels.triggerLabel}`}>{value ?? ''}</span>
      <span data-testid={`disabled-${labels.triggerLabel}`}>{String(Boolean(disabled))}</span>
      <span data-testid={`params-${labels.triggerLabel}`}>{JSON.stringify(params ?? null)}</span>
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
 * Controls the one-shot `meta` fetch behind the registry prefill (BR-4/A-5)
 * and the product-lines draft picker's label resolution (amendment rev.3):
 * both go through the generic `fetchForSelect`, mocked here per resource+id.
 */
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

const EMPTY_PAGE = { items: [], pagination: { offset: 0, limit: 25, total: 0 }, export_link: null }

/** The products-of-interest picker opens the shared confirm dialog, so its provider is required. */
function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>{children}</ConfirmDialogProvider>
    </QueryClientProvider>
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createOpportunityMock.mockReset()
  updateOpportunityMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: FULL_PERMISSIONS })

  fetchSystemStatusIdMock.mockReset()
  fetchSystemStatusIdMock.mockResolvedValue(null)

  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockImplementation(async (resource: string, params: { ids?: number[] }) => {
    if (resource === 'registries' && params?.ids?.includes(TEST_REGISTRY_WITH_DEFAULTS)) {
      return {
        ...EMPTY_PAGE,
        items: [
          {
            id: TEST_REGISTRY_WITH_DEFAULTS,
            label: 'Acme S.p.A.',
            meta: {
              commercial: { id: 71, name: 'Sara Conti' },
              reporter: { id: 81, name: 'Elio Fabbri' },
              supervisor: { id: 61, name: 'Ivo Bianchi' },
              // A-5: account managers inherited into manager_slots (gap-aware by position).
              managers: [
                { id: 91, name: 'Gina Manager', position: 1 },
                { id: 93, name: 'Turi Manager', position: 3 },
              ],
            },
          },
        ],
      }
    }
    if (resource === 'registries' && params?.ids?.includes(TEST_REGISTRY_WITHOUT_DEFAULTS)) {
      return {
        ...EMPTY_PAGE,
        items: [
          {
            id: TEST_REGISTRY_WITHOUT_DEFAULTS,
            label: 'Beta Srl',
            meta: { commercial: null, reporter: null, supervisor: null, managers: [] },
          },
        ],
      }
    }
    if (resource === 'business-functions' && params?.ids?.includes(TEST_BUSINESS_FUNCTION)) {
      return { ...EMPTY_PAGE, items: [{ id: TEST_BUSINESS_FUNCTION, label: 'Sales' }] }
    }
    if (resource === 'product-categories' && params?.ids?.includes(TEST_PRODUCT_CATEGORY)) {
      return { ...EMPTY_PAGE, items: [{ id: TEST_PRODUCT_CATEGORY, label: 'Consulting' }] }
    }
    return EMPTY_PAGE
  })
})

describe('OpportunityFormBody — fields render (AC-071)', () => {
  it('renders every relational select, with no name input (spec 0057 D-5)', async () => {
    render(<OpportunityForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Registry')).toBeInTheDocument())
    expect(screen.queryByRole('textbox', { name: 'Name' })).not.toBeInTheDocument()
    // Spec 0082: the status is COMPUTED, so the form shows a read-only badge
    // (here the create-mode placeholder), never a select.
    expect(screen.queryByTestId('select-Opportunity Status')).not.toBeInTheDocument()
    expect(screen.getByText('No status')).toBeInTheDocument()
    expect(screen.getByTestId('select-Contact')).toBeInTheDocument()
    expect(screen.getByTestId('select-Sales rep')).toBeInTheDocument()
    expect(screen.getByTestId('select-Reporter')).toBeInTheDocument()
    expect(screen.getByTestId('select-Source')).toBeInTheDocument()
    expect(screen.getByTestId('select-Operational site')).toBeInTheDocument()
    expect(screen.getByTestId('select-Supervisor')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Add account manager' })).toBeInTheDocument()
    // User directive 2026-07-29: the create form opens on ONE product-line row
    // (it used to render none until "Add" was clicked).
    expect(screen.getByRole('button', { name: 'Add product line' })).toBeInTheDocument()
    expect(screen.getByTestId('select-Business function 1')).toBeInTheDocument()
    expect(screen.queryByTestId('select-Business function 2')).not.toBeInTheDocument()
  })

  /** Directive 2026-07-21: supervisor is never required, in either mode (it derives from the linked Lead's Operatore, which may be empty). */
  it('never marks supervisor required, in create or edit mode', async () => {
    const create = render(
      <OpportunityForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(labelFor('Supervisor')).toBeInTheDocument())
    expect(labelFor('Supervisor')).not.toHaveTextContent('*')
    create.unmount()

    render(
      <OpportunityForm
        mode={{ type: 'edit', opportunity: editOpportunity() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(labelFor('Supervisor')).toHaveTextContent('Supervisor')
    expect(labelFor('Supervisor')).not.toHaveTextContent('*')
  })
})

describe('OpportunityFormBody — referent scoping + free commercial/reporter (AC-093)', () => {
  it('disables only the referent until a registry is chosen; commercial/reporter stay enabled', async () => {
    render(<OpportunityForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    // A-3: only referent is anagrafica-scoped; commercial/reporter are free.
    await waitFor(() => expect(screen.getByTestId('disabled-Contact')).toHaveTextContent('true'))
    expect(screen.getByTestId('disabled-Sales rep')).toHaveTextContent('false')
    expect(screen.getByTestId('disabled-Reporter')).toHaveTextContent('false')
  })

  // Requirement changed by directive 2026-07-29 (supersedes 2026-07-17): the
  // three roles are ALWAYS inherited from the anagrafica. The pickers stay
  // unscoped (A-3), only their initial value is now derived.
  it('scopes ONLY the referent by registry_id; commercial/reporter/supervisor receive no params but ARE inherited from the anagrafica', async () => {
    render(<OpportunityForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Registry')).toBeInTheDocument())
    screen.getByRole('button', { name: `select Registry ${TEST_REGISTRY_WITH_DEFAULTS}` }).click()

    await waitFor(() => expect(screen.getByTestId('disabled-Contact')).toHaveTextContent('false'))
    expect(screen.getByTestId('params-Contact')).toHaveTextContent(
      JSON.stringify({ registry_id: TEST_REGISTRY_WITH_DEFAULTS }),
    )
    // A-3: commercial/reporter are the whole platform list — no registry_id param.
    expect(screen.getByTestId('params-Sales rep')).toHaveTextContent('null')
    expect(screen.getByTestId('params-Reporter')).toHaveTextContent('null')

    await waitFor(() => expect(screen.getByTestId('value-Sales rep')).toHaveTextContent('71'))
    expect(screen.getByTestId('value-Reporter')).toHaveTextContent('81')
    expect(screen.getByTestId('value-Supervisor')).toHaveTextContent('61')
    // The referent is reset, not inherited: it stays anagrafica-scoped (BR-4).
    expect(screen.getByTestId('value-Contact')).toHaveTextContent('')
  })

  it('re-inherits the three roles on every registry change, clearing them for an anagrafica without defaults', async () => {
    render(<OpportunityForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Registry')).toBeInTheDocument())
    screen.getByRole('button', { name: `select Registry ${TEST_REGISTRY_WITH_DEFAULTS}` }).click()

    // The user manually picks a referent (scoped) and overrides the inherited roles.
    await waitFor(() => expect(screen.getByTestId('disabled-Contact')).toHaveTextContent('false'))
    screen.getByRole('button', { name: 'select Contact 1' }).click()
    screen.getByRole('button', { name: 'select Sales rep 1' }).click()
    screen.getByRole('button', { name: 'select Reporter 1' }).click()
    await waitFor(() => expect(screen.getByTestId('value-Sales rep')).toHaveTextContent('1'))

    // Changing the anagrafica resets the scoped referent AND re-derives the
    // three roles — the new one has none, so they end up empty.
    screen.getByRole('button', { name: `select Registry ${TEST_REGISTRY_WITHOUT_DEFAULTS}` }).click()

    await waitFor(() => expect(screen.getByTestId('value-Contact')).toHaveTextContent(''))
    expect(screen.getByTestId('value-Sales rep')).toHaveTextContent('')
    expect(screen.getByTestId('value-Reporter')).toHaveTextContent('')
    expect(screen.getByTestId('value-Supervisor')).toHaveTextContent('')
  })

  it('inherits the account managers of the chosen anagrafica into gap-aware slots, then clears them for one without (AC-095)', async () => {
    render(<OpportunityForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Registry')).toBeInTheDocument())
    screen.getByRole('button', { name: `select Registry ${TEST_REGISTRY_WITH_DEFAULTS}` }).click()

    // Positions 1 and 3 -> slots 1 and 3 filled, slot 2 an empty gap.
    await waitFor(() => expect(screen.getByTestId('value-Account manager 1')).toHaveTextContent('91'))
    expect(screen.getByTestId('value-Account manager 2')).toHaveTextContent('')
    expect(screen.getByTestId('value-Account manager 3')).toHaveTextContent('93')

    screen.getByRole('button', { name: `select Registry ${TEST_REGISTRY_WITHOUT_DEFAULTS}` }).click()

    // No managers on the new anagrafica -> slots cleared.
    await waitFor(() =>
      expect(screen.queryByTestId('value-Account manager 1')).not.toBeInTheDocument(),
    )
  })
})

describe('OpportunityFormBody — product lines (AC-106)', () => {
  it('scopes the row category by the row own business function', async () => {
    render(<OpportunityForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByRole('button', { name: 'Add product line' })).toBeInTheDocument())
    fireEvent.click(screen.getByRole('button', { name: 'Add product line' }))
    expect(screen.getByTestId('disabled-Product category 1')).toHaveTextContent('true')

    screen.getByRole('button', { name: `select Business function 1 ${TEST_BUSINESS_FUNCTION}` }).click()

    await waitFor(() => expect(screen.getByTestId('disabled-Product category 1')).toHaveTextContent('false'))
    expect(screen.getByTestId('scope-Product category 1')).toHaveTextContent(
      JSON.stringify({ business_function_id: TEST_BUSINESS_FUNCTION, root_category_id: null }),
    )
  })

  it('blocks the submit and shows an error when a row is left incomplete', async () => {
    render(<OpportunityForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByRole('button', { name: 'Add product line' })).toBeInTheDocument())
    fireEvent.click(screen.getByRole('button', { name: 'Add product line' }))
    screen.getByRole('button', { name: `select Business function 1 ${TEST_BUSINESS_FUNCTION}` }).click()
    await waitFor(() => expect(screen.getByTestId('disabled-Product category 1')).toHaveTextContent('false'))
    // The row's category is left unset on purpose.

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() =>
      expect(
        screen.getByText('Each row requires both a business function and a product category.'),
      ).toBeInTheDocument(),
    )
    expect(createOpportunityMock).not.toHaveBeenCalled()
  })
})

describe('OpportunityFormBody — field permissions (AC-074)', () => {
  it('does not render a field marked hidden (visible: false)', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: {
        ...FULL_PERMISSIONS,
        fields: {
          supervisor_id: {
            visible: false,
            hidden: true,
            editable: false,
            readonly: false,
            required: false,
            disabled: false,
          },
        },
      },
    })

    render(<OpportunityForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Registry')).toBeInTheDocument())
    expect(screen.queryByTestId('select-Supervisor')).not.toBeInTheDocument()
  })

  it('renders a non-editable field disabled rather than hiding it', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: {
        ...FULL_PERMISSIONS,
        fields: {
          source_id: {
            visible: true,
            hidden: false,
            editable: false,
            readonly: true,
            required: false,
            disabled: false,
          },
        },
      },
    })

    render(<OpportunityForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    const source = await screen.findByTestId('disabled-Source')
    expect(source).toHaveTextContent('true')
  })
})
