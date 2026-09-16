import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { OpportunityForm } from '@/features/opportunities/opportunity-form'
import type { ResourceMeta } from '@/features/authorization/types'

/**
 * Spec 0132 (supersedes AC-106): the row's two-step pick — root category then
 * one of its descendants, no business-function select any more. Split out of
 * `opportunity-form-body.test.tsx` when it crossed the 500-line hard limit
 * (engineering.md §6); both files render the SAME `OpportunityForm`, so they
 * duplicate the small mock/wrapper setup rather than share module state.
 */

const createOpportunityMock = vi.fn()

/**
 * The row's category picker reads the category TREE (user directive
 * 2026-08-03); this suite is about the surrounding wiring, so it stands in
 * for the picker with the shared double (also used by
 * `product-lines-field.test.tsx` against the real component).
 */
vi.mock('@/features/product-lines/product-category-tree-select', async () =>
  await import('@/features/product-lines/product-category-tree-select-stub'))

const TEST_ROOT_CATEGORY = 30

/** Fixed selection id exposed by the root-category picker's double, so its onChange is exercisable. */
const SELECT_IDS: Record<string, number[]> = {
  'Parent category 1': [TEST_ROOT_CATEGORY],
}

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

/**
 * Every OTHER relation field (Registry, Contact, Sales rep...) must stay
 * inert too: without this double they render for real, including their own
 * quick-create affordance (`<Can>`, which throws outside an `AuthProvider`)
 * — this suite is only about the product-lines row, mirrors
 * `opportunity-form-body.test.tsx`'s own stub.
 */
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    value,
    disabled,
    labels,
  }: {
    value: number | null
    onChange: (value: number | null) => void
    disabled?: boolean
    labels: { triggerLabel: string }
  }) => (
    <div data-testid={`select-${labels.triggerLabel}`}>
      <span data-testid={`value-${labels.triggerLabel}`}>{value ?? ''}</span>
      <span data-testid={`disabled-${labels.triggerLabel}`}>{String(Boolean(disabled))}</span>
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
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: FULL_PERMISSIONS })
  fetchSystemStatusIdMock.mockReset()
  fetchSystemStatusIdMock.mockResolvedValue(null)
})

describe('OpportunityFormBody — product lines (spec 0132)', () => {
  it("scopes the row category by the row's own root category", async () => {
    render(<OpportunityForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByRole('button', { name: 'Add product line' })).toBeInTheDocument())
    fireEvent.click(screen.getByRole('button', { name: 'Add product line' }))
    expect(screen.getByTestId('disabled-Product category 1')).toHaveTextContent('true')

    screen.getByRole('button', { name: `select Parent category 1 ${TEST_ROOT_CATEGORY}` }).click()

    await waitFor(() => expect(screen.getByTestId('disabled-Product category 1')).toHaveTextContent('false'))
    expect(screen.getByTestId('scope-Product category 1')).toHaveTextContent(
      JSON.stringify({ kind: 'root', rootCategoryId: TEST_ROOT_CATEGORY }),
    )
  })

  it('blocks the submit and shows an error when a row is left incomplete', async () => {
    render(<OpportunityForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.getByRole('button', { name: 'Add product line' })).toBeInTheDocument())
    fireEvent.click(screen.getByRole('button', { name: 'Add product line' }))
    screen.getByRole('button', { name: `select Parent category 1 ${TEST_ROOT_CATEGORY}` }).click()
    await waitFor(() => expect(screen.getByTestId('disabled-Product category 1')).toHaveTextContent('false'))
    // The row's category is left unset on purpose.

    fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))

    await waitFor(() =>
      expect(
        screen.getByText('Each row requires both a parent category and a product category.'),
      ).toBeInTheDocument(),
    )
    expect(createOpportunityMock).not.toHaveBeenCalled()
  })
})
