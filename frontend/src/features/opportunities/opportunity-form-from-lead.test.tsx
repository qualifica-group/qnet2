import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { OpportunityForm } from '@/features/opportunities/opportunity-form'
import type { ResourceMeta } from '@/features/authorization/types'

/**
 * AC-075 (spec 0040 MT-6): the `?lead_id=N` deep-link create-from-lead mode —
 * locked fields precompiled + forceDisabled, origin banner shown, free fields
 * stay editable, the derived product-line row seeds already editable/removable.
 * Split out of `opportunity-form-body.test.tsx` for file size (engineering.md
 * §6) — every other field's own behavior is covered there; the in-form "Lead"
 * select (AC-086/087/088, spec 0044 AC-025/034 supervisor prefill) is covered
 * separately in `opportunity-lead-selection.test.tsx`.
 */

/**
 * The row's category picker reads the category TREE (user directive
 * 2026-08-03) and mounts its own quick-create affordance; this suite is about
 * the surrounding form, so it stands in for the picker with the shared double.
 */
vi.mock('@/features/product-lines/product-category-tree-select', async () =>
  await import('@/features/product-lines/product-category-tree-select-stub'))

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

/** Stubs every single-select field, keyed by its accessible trigger label (mirrors `opportunity-form-body.test.tsx`). */
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    value,
    disabled,
    labels,
  }: {
    value: number | null
    disabled?: boolean
    labels: { triggerLabel: string }
  }) => (
    <div data-testid={`select-${labels.triggerLabel}`}>
      <span data-testid={`value-${labels.triggerLabel}`}>{value ?? ''}</span>
      <span data-testid={`disabled-${labels.triggerLabel}`}>{String(Boolean(disabled))}</span>
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
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: FULL_PERMISSIONS })

  fetchSystemStatusIdMock.mockReset()
  fetchSystemStatusIdMock.mockResolvedValue(null)

  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockResolvedValue(EMPTY_PAGE)
})

/** AC-075: create-from-lead mode (spec 0040 MT-6) — locked fields precompiled + forceDisabled, banner shown, free fields stay editable. */
describe('OpportunityFormBody — create from lead (BR-1/BR-2, AC-075)', () => {
  it('shows the origin banner, locks the derived fields precompiled, and seeds the editable product-line row', async () => {
    render(
      <OpportunityForm
        mode={{
          type: 'create',
          fromLead: {
            leadId: 9,
            values: {
              // spec 0041 D-3/AC-050: no longer a derived field.
              referent_id: null,
              source_id: 20,
              registry_id: 30,
              operational_site_id: null,
              general_notes: null,
            },
            references: {
              source: { id: 20, name: 'Web' },
              registry: { id: 30, name: 'Acme S.p.A.' },
              operational_site: null,
            },
            lockedFields: ['registry_id', 'source_id'],
            productLines: [
              {
                id: 900,
                business_function: { id: 40, name: 'Sales' },
                product_category: { id: 50, name: 'Consulting' },
              },
            ],
            // Directive 2026-07-21: no Operator on this fixture's lead — the
            // first "Gestore Account" slot stays empty (not under test here).
            managerSlots: [],
            managerRefs: [],
          },
        }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(screen.getByTestId('select-Registry')).toBeInTheDocument())
    // AC-051: the origin banner sources its name from the registry, not the referent.
    expect(screen.getByRole('status')).toHaveTextContent('Acme S.p.A.')

    expect(screen.getByTestId('value-Registry')).toHaveTextContent('30')
    expect(screen.getByTestId('disabled-Registry')).toHaveTextContent('true')
    // AC-051: referent_id is no longer derived/locked by the lead — free and
    // editable, gated only by the registry now being chosen.
    expect(screen.getByTestId('value-Contact')).toHaveTextContent('')
    expect(screen.getByTestId('disabled-Contact')).toHaveTextContent('false')
    expect(screen.getByTestId('value-Source')).toHaveTextContent('20')
    expect(screen.getByTestId('disabled-Source')).toHaveTextContent('true')

    // Amendment rev.3 (AC-102/103): the seeded row is editable/removable — never disabled.
    expect(screen.getByTestId('value-Business function 1')).toHaveTextContent('40')
    expect(screen.getByTestId('disabled-Business function 1')).toHaveTextContent('false')
    expect(screen.getByTestId('value-Product category 1')).toHaveTextContent('50')
    expect(screen.getByTestId('disabled-Product category 1')).toHaveTextContent('false')
    expect(screen.getByRole('button', { name: 'Remove product line' })).toBeInTheDocument()
  })

  it('renders no banner and no locked field for a plain manual create', async () => {
    render(<OpportunityForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByTestId('select-Registry')).toBeInTheDocument())
    expect(screen.queryByRole('status')).not.toBeInTheDocument()
    expect(screen.getByTestId('disabled-Registry')).toHaveTextContent('false')
  })
})
