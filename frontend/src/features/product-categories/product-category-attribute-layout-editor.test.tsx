import type { ReactNode } from 'react'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import i18n from '@/i18n'
import { ProductCategoryAttributeLayoutEditor } from '@/features/product-categories/product-category-attribute-layout-editor'
import type { AttributeLayoutData } from '@/features/product-categories/types'

/**
 * MT-3.2 mount wiring (spec 0062), relocated to the category EDIT form: the
 * (context × form_mode) selector loads its own layout on each combination
 * and PUTs the currently edited draft on Save. AC-009/AC-010/AC-011 (the
 * configurator itself) are covered in `features/attributes/layout-configurator/`.
 */

const fetchAttributeLayoutMock = vi.fn<
  (categoryId: number, context: string, formMode: string) => Promise<AttributeLayoutData>
>()
const saveAttributeLayoutMock = vi.fn()

vi.mock('@/features/product-categories/api', () => ({
  fetchAttributeLayout: (...args: [number, string, string]) => fetchAttributeLayoutMock(...args),
  saveAttributeLayout: (...args: unknown[]) => saveAttributeLayoutMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

function response(overrides: Partial<AttributeLayoutData> = {}): AttributeLayoutData {
  return {
    layout: null,
    attributes: [
      {
        id: 1,
        code: 'sku',
        name: 'SKU',
        type: 'text',
        description: null,
        help_text: null,
        placeholder: null,
        icon: null,
        config: null,
        relation_target: null,
        is_required: false,
        sort_order: 0,
        inherited: false,
        context: 'product',
        options: [],
      },
    ],
    ...overrides,
  }
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchAttributeLayoutMock.mockReset()
  saveAttributeLayoutMock.mockReset()
  fetchAttributeLayoutMock.mockResolvedValue(response())
})

describe('ProductCategoryAttributeLayoutEditor', () => {
  it('loads the (product, create) layout by default and renders its attributes in the palette', async () => {
    render(<ProductCategoryAttributeLayoutEditor categoryId={7} canEdit />, { wrapper: wrapper() })

    await waitFor(() => expect(fetchAttributeLayoutMock).toHaveBeenCalledWith(7, 'product', 'create'))
    // "SKU" also appears in the live preview's flat-fallback field label — assert on the palette specifically.
    const palette = (await screen.findByText('Unplaced attributes')).closest('div') as HTMLElement
    expect(within(palette).getByText('SKU')).toBeInTheDocument()
  })

  it('switching context re-fetches that context’s layout', async () => {
    render(<ProductCategoryAttributeLayoutEditor categoryId={7} canEdit />, { wrapper: wrapper() })
    await waitFor(() => expect(fetchAttributeLayoutMock).toHaveBeenCalledWith(7, 'product', 'create'))

    // Radix `Tabs.Trigger` switches on `mousedown`, not `click` (see @radix-ui/react-tabs).
    fireEvent.mouseDown(screen.getByRole('tab', { name: 'Opportunity' }))

    await waitFor(() => expect(fetchAttributeLayoutMock).toHaveBeenCalledWith(7, 'opportunity', 'create'))
  })

  it('switching form mode re-fetches that mode’s layout', async () => {
    render(<ProductCategoryAttributeLayoutEditor categoryId={7} canEdit />, { wrapper: wrapper() })
    await waitFor(() => expect(fetchAttributeLayoutMock).toHaveBeenCalledWith(7, 'product', 'create'))

    fireEvent.click(screen.getByRole('combobox', { name: 'Form mode' }))
    fireEvent.click(await screen.findByRole('option', { name: 'Edit' }))

    await waitFor(() => expect(fetchAttributeLayoutMock).toHaveBeenCalledWith(7, 'product', 'edit'))
  })

  it('Save PUTs the current draft for the active (context, form_mode)', async () => {
    saveAttributeLayoutMock.mockResolvedValue(null)
    render(<ProductCategoryAttributeLayoutEditor categoryId={7} canEdit />, { wrapper: wrapper() })
    await waitFor(() => expect(fetchAttributeLayoutMock).toHaveBeenCalled())

    fireEvent.click(await screen.findByRole('button', { name: 'Save layout' }))

    await waitFor(() =>
      expect(saveAttributeLayoutMock).toHaveBeenCalledWith(7, 'product', 'create', { sections: [] }),
    )
  })

  it('hides the Save action when the actor lacks update permission', async () => {
    render(<ProductCategoryAttributeLayoutEditor categoryId={7} canEdit={false} />, { wrapper: wrapper() })
    await waitFor(() => expect(fetchAttributeLayoutMock).toHaveBeenCalled())

    expect(screen.queryByRole('button', { name: 'Save layout' })).not.toBeInTheDocument()
  })
})
