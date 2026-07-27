import type { ReactNode } from 'react'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { ProductCategoryAttributeLayoutPreview } from '@/features/product-categories/product-category-attribute-layout-preview'
import type { AttributeLayoutData } from '@/features/product-categories/types'

/**
 * Spec 0062 follow-up: the category detail no longer hosts authoring — only
 * a read-only preview of whatever layout is persisted for the selected
 * (context × form_mode). Mirrors
 * `product-category-attribute-layout-editor.test.tsx`'s fetch wiring, minus
 * every Save/edit assertion, plus the dedicated empty state.
 */

const fetchAttributeLayoutMock = vi.fn<
  (categoryId: number, context: string, formMode: string) => Promise<AttributeLayoutData>
>()

vi.mock('@/features/product-categories/api', () => ({
  fetchAttributeLayout: (...args: [number, string, string]) => fetchAttributeLayoutMock(...args),
  saveAttributeLayout: vi.fn(),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const SKU_ATTRIBUTE: AttributeLayoutData['attributes'][number] = {
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
}

function response(overrides: Partial<AttributeLayoutData> = {}): AttributeLayoutData {
  return {
    layout: null,
    attributes: [SKU_ATTRIBUTE],
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
  fetchAttributeLayoutMock.mockResolvedValue(response())
})

describe('ProductCategoryAttributeLayoutPreview', () => {
  it('loads the (product, create) layout by default', async () => {
    render(<ProductCategoryAttributeLayoutPreview categoryId={7} />, { wrapper: wrapper() })

    await waitFor(() => expect(fetchAttributeLayoutMock).toHaveBeenCalledWith(7, 'product', 'create'))
  })

  it('switching context or form mode re-fetches that combination’s layout', async () => {
    render(<ProductCategoryAttributeLayoutPreview categoryId={7} />, { wrapper: wrapper() })
    await waitFor(() => expect(fetchAttributeLayoutMock).toHaveBeenCalledWith(7, 'product', 'create'))

    fireEvent.mouseDown(screen.getByRole('tab', { name: 'Opportunity' }))
    await waitFor(() => expect(fetchAttributeLayoutMock).toHaveBeenCalledWith(7, 'opportunity', 'create'))

    fireEvent.click(screen.getByRole('combobox', { name: 'Form mode' }))
    fireEvent.click(await screen.findByRole('option', { name: 'Edit' }))
    await waitFor(() => expect(fetchAttributeLayoutMock).toHaveBeenCalledWith(7, 'opportunity', 'edit'))
  })

  it('never renders a Save action — no editing/authoring in the detail preview', async () => {
    render(<ProductCategoryAttributeLayoutPreview categoryId={7} />, { wrapper: wrapper() })
    await waitFor(() => expect(fetchAttributeLayoutMock).toHaveBeenCalled())

    expect(screen.queryByRole('button', { name: 'Save layout' })).not.toBeInTheDocument()
  })

  it('shows the empty state when no layout is configured for the combination', async () => {
    render(<ProductCategoryAttributeLayoutPreview categoryId={7} />, { wrapper: wrapper() })

    expect(await screen.findByText('No layout configured for this combination.')).toBeInTheDocument()
  })

  it('renders the persisted layout, read-only, when one is configured', async () => {
    fetchAttributeLayoutMock.mockResolvedValue(
      response({
        layout: {
          sections: [
            {
              id: 's1',
              title: 'Identification',
              description: null,
              variant: 'default',
              collapsible: false,
              default_collapsed: false,
              columns: 1,
              sort_order: 0,
              rows: [{ id: 'r1', items: [{ attribute_code: 'sku', width: 'full' }] }],
            },
          ],
        },
      }),
    )

    render(<ProductCategoryAttributeLayoutPreview categoryId={7} />, { wrapper: wrapper() })

    expect(await screen.findByRole('heading', { name: 'Identification' })).toBeInTheDocument()
    const field = screen.getByRole('textbox', { name: 'SKU' })
    expect(field).toHaveAttribute('readonly')
  })
})
