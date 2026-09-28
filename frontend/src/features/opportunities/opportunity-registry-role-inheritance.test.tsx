import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { OpportunityForm } from '@/features/opportunities/opportunity-form'
import type { ResourceMeta } from '@/features/authorization/types'

/**
 * The anagrafica pick (split from `opportunity-form-body.test.tsx` for size,
 * engineering.md §6): AC-072/AC-093 referent scoping, the roles and G.A. slots
 * inherited from the anagrafica (directive 2026-07-29, AC-095), and the
 * confirmation that guards the values the user entered BEFORE picking it —
 * the team section sits above the client section (user report 2026-09-28:
 * the team typed first was wiped and never saved).
 */

const createOpportunityMock = vi.fn()
const updateOpportunityMock = vi.fn()

/**
 * The row's category picker reads the category TREE (user directive
 * 2026-08-03) and mounts its own quick-create affordance; this suite is about
 * the surrounding form, so it stands in for the picker with the shared double.
 * The row's own two-step pick (root category, spec 0132) is exercised against
 * these same doubles in `opportunity-form-body-product-lines.test.tsx`, split
 * out for size (engineering.md §6) — this file only asserts the row RENDERS.
 */
vi.mock('@/features/product-lines/product-category-tree-select', async () =>
  await import('@/features/product-lines/product-category-tree-select-stub'))

/** The row's FIRST step (spec 0132), same double style as the category picker above. */
vi.mock('@/features/product-lines/product-category-root-select', () => ({
  ProductCategoryRootSelect: ({
    value,
    onChange,
    disabled,
    triggerLabel,
  }: {
    value: number | null
    onChange: (rootCategoryId: number) => void
    disabled?: boolean
    triggerLabel: string
  }) => (
    <div data-testid={`select-${triggerLabel}`}>
      <span data-testid={`value-${triggerLabel}`}>{value ?? ''}</span>
      <span data-testid={`disabled-${triggerLabel}`}>{String(Boolean(disabled))}</span>
      {(SELECT_IDS[triggerLabel] ?? [1]).map((id) => (
        <button key={id} type="button" onClick={() => onChange(id)}>
          {`select ${triggerLabel} ${id}`}
        </button>
      ))}
    </div>
  ),
}))

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
const TEST_PRODUCT_CATEGORY = 500
const TEST_ROOT_CATEGORY = 30


/** Fixed selection ids exposed per field (by accessible trigger label), so BR-4 side effects are exercisable without a real dropdown. */
const SELECT_IDS: Record<string, number[]> = {
  Registry: [TEST_REGISTRY_WITH_DEFAULTS, TEST_REGISTRY_WITHOUT_DEFAULTS],
  'Parent category 1': [TEST_ROOT_CATEGORY],
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
    return EMPTY_PAGE
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

  // Requirement changed by user decision 2026-09-28: values the user entered
  // are replaced only after confirming.
  it('re-inherits the three roles on every registry change once the user confirms replacing their own', async () => {
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

    // Changing the anagrafica resets the scoped referent AND, once confirmed,
    // re-derives the three roles — the new one has none, so they end up empty.
    screen.getByRole('button', { name: `select Registry ${TEST_REGISTRY_WITHOUT_DEFAULTS}` }).click()
    fireEvent.click(await screen.findByRole('button', { name: 'Replace' }))

    await waitFor(() => expect(screen.getByTestId('value-Sales rep')).toHaveTextContent(''))
    expect(screen.getByTestId('value-Contact')).toHaveTextContent('')
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

    // The values came from the previous anagrafica, not from the user: replaced
    // without asking. Requirement changed 2026-09-28: the four G.A. cards stay,
    // empty, like on a blank create form.
    await waitFor(() => expect(screen.getByTestId('value-Account manager 1')).toHaveTextContent(''))
    expect(screen.getByTestId('value-Account manager 3')).toHaveTextContent('')
    expect(screen.getByTestId('value-Account manager 4')).toHaveTextContent('')
    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()
  })
})

describe('OpportunityFormBody — team entered before the anagrafica (user report 2026-09-28)', () => {
  async function enterTeamThenPickRegistry() {
    render(<OpportunityForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Supervisor')).toBeInTheDocument())
    screen.getByRole('button', { name: 'select Supervisor 1' }).click()
    screen.getByRole('button', { name: 'select Account manager 2 1' }).click()
    await waitFor(() => expect(screen.getByTestId('value-Account manager 2')).toHaveTextContent('1'))

    screen.getByRole('button', { name: `select Registry ${TEST_REGISTRY_WITH_DEFAULTS}` }).click()
  }

  it('keeps the team the user entered when they choose to, still inheriting the empty roles', async () => {
    await enterTeamThenPickRegistry()

    fireEvent.click(await screen.findByRole('button', { name: 'Keep mine' }))

    await waitFor(() => expect(screen.getByTestId('value-Sales rep')).toHaveTextContent('71'))
    expect(screen.getByTestId('value-Reporter')).toHaveTextContent('81')
    expect(screen.getByTestId('value-Supervisor')).toHaveTextContent('1')
    expect(screen.getByTestId('value-Account manager 1')).toHaveTextContent('')
    expect(screen.getByTestId('value-Account manager 2')).toHaveTextContent('1')
  })

  it("replaces the team with the anagrafica's when the user confirms", async () => {
    await enterTeamThenPickRegistry()

    fireEvent.click(await screen.findByRole('button', { name: 'Replace' }))

    await waitFor(() => expect(screen.getByTestId('value-Supervisor')).toHaveTextContent('61'))
    expect(screen.getByTestId('value-Account manager 1')).toHaveTextContent('91')
    expect(screen.getByTestId('value-Account manager 2')).toHaveTextContent('')
    expect(screen.getByTestId('value-Account manager 3')).toHaveTextContent('93')
  })
})
