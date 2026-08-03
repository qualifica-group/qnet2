import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
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
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>{children}</ConfirmDialogProvider>
    </QueryClientProvider>
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

describe('ProductsOfInterestField (user directive 2026-07-22)', () => {
  it('scopes the options to the opportunity categories by default', async () => {
    renderField({ categoryIds: [7, 9] })

    await openPicker()

    expect(fetchForSelectMock).toHaveBeenCalledWith(
      'products',
      expect.objectContaining({ params: { category_ids: [7, 9] } }),
    )
    expect(screen.getByText("Only products of this opportunity's categories.")).toBeInTheDocument()
  })

  it('unlocking asks for confirmation FIRST, stating that a cross-category product adds its row', async () => {
    renderField()

    fireEvent.click(screen.getByRole('button', { name: 'Show all products' }))

    const dialog = await screen.findByRole('alertdialog')
    expect(dialog).toHaveTextContent(
      "Picking a product from another business function and product category adds that pair to this opportunity's business functions and product categories.",
    )
    // Nothing is unlocked until the dialog is answered: the hint still reads scoped.
    expect(screen.getByText("Only products of this opportunity's categories.")).toBeInTheDocument()
  })

  it('cancelling the dialog keeps the picker scoped', async () => {
    renderField()

    fireEvent.click(screen.getByRole('button', { name: 'Show all products' }))
    fireEvent.click(await screen.findByRole('button', { name: 'Cancel' }))

    await waitFor(() => expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument())
    expect(screen.getByRole('button', { name: 'Show all products' })).toBeInTheDocument()
  })

  it('confirming drops the category scope and offers to re-lock it', async () => {
    renderField()

    fireEvent.click(screen.getByRole('button', { name: 'Show all products' }))
    const dialog = await screen.findByRole('alertdialog')
    fireEvent.click(within(dialog).getByRole('button', { name: 'Show all products' }))

    await waitFor(() =>
      expect(screen.getByRole('button', { name: "Limit to the opportunity's categories" })).toBeInTheDocument(),
    )

    await openPicker()
    expect(fetchForSelectMock).toHaveBeenCalledWith(
      'products',
      expect.not.objectContaining({ params: expect.anything() }),
    )
  })

  it('disables the picker when locked with no category to scope to', () => {
    renderField({ categoryIds: [] })

    expect(screen.getByRole('button', { name: 'Products of interest' })).toBeDisabled()
    expect(
      screen.getByText('Add a business function with its product category first, or unlock the whole catalogue.'),
    ).toBeInTheDocument()
  })
})

// ---------------------------------------------------------------------------
// Spec 0075, D-4 — the locked variant (request-management)
// ---------------------------------------------------------------------------

describe('ProductsOfInterestField with lockScope (spec 0075, AC-016)', () => {
  it('offers no unlock at all: the module refuses what falls outside the scope', async () => {
    renderField({ lockScope: true, categoryIds: [7] })

    await openPicker()

    expect(screen.queryByRole('button', { name: 'Show all products' })).not.toBeInTheDocument()
    expect(
      screen.getByText("Only products of this request's product categories."),
    ).toBeInTheDocument()
    expect(fetchForSelectMock).toHaveBeenCalledWith(
      'products',
      expect.objectContaining({ params: { category_ids: [7] } }),
    )
  })

  it('keeps offering the unlock without it (the opportunities form)', () => {
    renderField({ categoryIds: [7] })

    expect(screen.getByRole('button', { name: 'Show all products' })).toBeInTheDocument()
  })
})
