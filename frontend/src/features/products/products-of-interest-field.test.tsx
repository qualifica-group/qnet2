import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import i18n from '@/i18n'
import { ProductsOfInterestField } from '@/features/products/products-of-interest-field'

const fetchForSelectMock = vi.fn()

vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>(
    '@/features/for-select/api',
  )
  return {
    ...actual,
    fetchForSelect: (resource: string, params: unknown) => fetchForSelectMock(resource, params),
  }
})

const EMPTY_PAGE = { items: [], pagination: { offset: 0, limit: 25, total: 0 }, export_link: null }

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function renderField(props: Partial<Parameters<typeof ProductsOfInterestField>[0]> = {}) {
  return render(
    <ProductsOfInterestField value={[]} onChange={vi.fn()} categoryIds={[7]} {...props} />,
    { wrapper: wrapper() },
  )
}

/** Opens the picker popup, which is what triggers the paginated query. */
async function openPicker() {
  fireEvent.click(screen.getByRole('button', { name: 'Products of interest' }))
  await waitFor(() => expect(fetchForSelectMock).toHaveBeenCalled())
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockResolvedValue(EMPTY_PAGE)
})

/**
 * Requirement CHANGED (user directive 2026-08-05): the whole-catalogue escape
 * spec 0075's D-4 kept for the opportunities form is gone from BOTH modules —
 * a product outside the record's own product categories is refused server-side
 * (ProductCategoryCoherence), so the picker never offers one.
 */
describe('ProductsOfInterestField (user directive 2026-07-22)', () => {
  it('scopes the options to the record categories, and says so', async () => {
    renderField({ categoryIds: [7, 9] })

    await openPicker()

    expect(fetchForSelectMock).toHaveBeenCalledWith(
      'products',
      expect.objectContaining({ params: { category_ids: [7, 9] } }),
    )
    expect(
      screen.getByText('Only products of the product categories selected above.'),
    ).toBeInTheDocument()
  })

  it('offers no way out of that scope: the picker trigger is the only control', () => {
    renderField({ categoryIds: [7] })

    expect(screen.getAllByRole('button')).toHaveLength(1)
    expect(screen.getByRole('button', { name: 'Products of interest' })).toBeInTheDocument()
  })

  it('disables the picker when there is no category to scope to', () => {
    renderField({ categoryIds: [] })

    expect(screen.getByRole('button', { name: 'Products of interest' })).toBeDisabled()
    expect(
      screen.getByText('Add a business function with its product category first.'),
    ).toBeInTheDocument()
  })
})
