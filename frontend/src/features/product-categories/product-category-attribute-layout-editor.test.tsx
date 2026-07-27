import type { ReactNode } from 'react'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { ProductCategoryAttributeLayoutEditor } from '@/features/product-categories/product-category-attribute-layout-editor'
import type { LayoutBlob } from '@/features/attributes/attribute-layout-types'
import type { AttributeLayoutData } from '@/features/product-categories/types'

/**
 * MT-3.2 mount wiring, now hosted by the dedicated attribute-layout Sheet
 * (spec 0062 revision): the (context × scope) selector loads its own layout
 * on each combination and PUTs the currently edited draft on Save. The Sheet
 * only opens for actors the backend grants the "layout" row action to, so
 * this component always renders its Save (no `canEdit` prop). D3 revised: the
 * default scope is the shared "All modes" layout, and a per-mode scope is
 * read-only until explicitly customized. AC-009/AC-010/AC-011 (the
 * configurator itself) are covered in `features/attributes/layout-configurator/`.
 */

const fetchAttributeLayoutMock = vi.fn<
  (categoryId: number, context: string, scope: string) => Promise<AttributeLayoutData>
>()
const saveAttributeLayoutMock = vi.fn()

vi.mock('@/features/product-categories/api', () => ({
  fetchAttributeLayout: (...args: [number, string, string]) => fetchAttributeLayoutMock(...args),
  saveAttributeLayout: (...args: unknown[]) => saveAttributeLayoutMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

/** A one-section layout holding the `sku` attribute — enough to tell "inherited" from "empty". */
const SKU_LAYOUT: LayoutBlob = {
  sections: [
    {
      id: 'section-1',
      title: 'Identification',
      description: null,
      variant: 'default',
      collapsible: false,
      default_collapsed: false,
      columns: 1,
      sort_order: 0,
      rows: [{ id: 'row-1', items: [{ attribute_code: 'sku', width: 'full' }] }],
    },
  ],
}

function response(overrides: Partial<AttributeLayoutData> = {}): AttributeLayoutData {
  return {
    layout: null,
    inherited: null,
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
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>{children}</ConfirmDialogProvider>
    </QueryClientProvider>
  )
}

/** Picks a scope in the form-mode select and waits for its load. */
async function selectScope(scopeLabel: string) {
  fireEvent.click(screen.getByRole('combobox', { name: 'Form mode' }))
  fireEvent.click(await screen.findByRole('option', { name: scopeLabel }))
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

const onCancelMock = vi.fn()

beforeEach(() => {
  fetchAttributeLayoutMock.mockReset()
  saveAttributeLayoutMock.mockReset()
  onCancelMock.mockReset()
  fetchAttributeLayoutMock.mockResolvedValue(response())
})

describe('ProductCategoryAttributeLayoutEditor', () => {
  it('loads the (product, all modes) layout by default and renders its attributes in the palette', async () => {
    render(<ProductCategoryAttributeLayoutEditor categoryId={7} onCancel={onCancelMock} />, { wrapper: wrapper() })

    await waitFor(() => expect(fetchAttributeLayoutMock).toHaveBeenCalledWith(7, 'product', 'all'))
    // "SKU" also appears in the live preview's flat-fallback field label — assert on the palette specifically.
    const palette = (await screen.findByText('Unplaced attributes')).closest('div') as HTMLElement
    expect(within(palette).getByText('SKU')).toBeInTheDocument()
  })

  it('switching context re-fetches that context’s layout', async () => {
    render(<ProductCategoryAttributeLayoutEditor categoryId={7} onCancel={onCancelMock} />, { wrapper: wrapper() })
    await waitFor(() => expect(fetchAttributeLayoutMock).toHaveBeenCalledWith(7, 'product', 'all'))

    // Radix `Tabs.Trigger` switches on `mousedown`, not `click` (see @radix-ui/react-tabs).
    fireEvent.mouseDown(screen.getByRole('tab', { name: 'Opportunity' }))

    await waitFor(() => expect(fetchAttributeLayoutMock).toHaveBeenCalledWith(7, 'opportunity', 'all'))
  })

  it('switching form mode re-fetches that mode’s layout', async () => {
    render(<ProductCategoryAttributeLayoutEditor categoryId={7} onCancel={onCancelMock} />, { wrapper: wrapper() })
    await waitFor(() => expect(fetchAttributeLayoutMock).toHaveBeenCalledWith(7, 'product', 'all'))

    await selectScope('Edit')

    await waitFor(() => expect(fetchAttributeLayoutMock).toHaveBeenCalledWith(7, 'product', 'edit'))
  })

  it('Save PUTs the current draft for the active (context, scope) and keeps the panel open', async () => {
    saveAttributeLayoutMock.mockResolvedValue(null)
    render(<ProductCategoryAttributeLayoutEditor categoryId={7} onCancel={onCancelMock} />, { wrapper: wrapper() })
    await waitFor(() => expect(fetchAttributeLayoutMock).toHaveBeenCalled())

    fireEvent.click(await screen.findByRole('button', { name: 'Save layout' }))

    await waitFor(() => expect(saveAttributeLayoutMock).toHaveBeenCalledWith(7, 'product', 'all', { sections: [] }))
    expect(onCancelMock).not.toHaveBeenCalled()
  })

  it('a mode with no override of its own shows the inherited shared layout, not editable', async () => {
    fetchAttributeLayoutMock.mockImplementation(async (_id, _context, scope) =>
      scope === 'all' ? response({ layout: SKU_LAYOUT }) : response({ inherited: SKU_LAYOUT }),
    )
    render(<ProductCategoryAttributeLayoutEditor categoryId={7} onCancel={onCancelMock} />, { wrapper: wrapper() })
    await waitFor(() => expect(fetchAttributeLayoutMock).toHaveBeenCalledWith(7, 'product', 'all'))

    await selectScope('Edit')

    expect(await screen.findByText('This mode uses the “All modes” layout.')).toBeInTheDocument()
    // The inherited sections are shown, but Save cannot mint an override the actor never asked for.
    expect(screen.getAllByDisplayValue('Identification').length).toBeGreaterThan(0)
    expect(screen.getByRole('button', { name: 'Save layout' })).toBeDisabled()
  })

  it('“Customize this mode” makes the inherited layout editable and Save PUTs it as that mode’s override', async () => {
    fetchAttributeLayoutMock.mockImplementation(async (_id, _context, scope) =>
      scope === 'all' ? response({ layout: SKU_LAYOUT }) : response({ inherited: SKU_LAYOUT }),
    )
    saveAttributeLayoutMock.mockResolvedValue(SKU_LAYOUT)
    render(<ProductCategoryAttributeLayoutEditor categoryId={7} onCancel={onCancelMock} />, { wrapper: wrapper() })
    await selectScope('Edit')

    fireEvent.click(await screen.findByRole('button', { name: 'Customize this mode' }))
    fireEvent.click(screen.getByRole('button', { name: 'Save layout' }))

    await waitFor(() => expect(saveAttributeLayoutMock).toHaveBeenCalledWith(7, 'product', 'edit', SKU_LAYOUT))
  })

  it('“Back to all modes” deletes the override after confirmation', async () => {
    fetchAttributeLayoutMock.mockImplementation(async (_id, _context, scope) =>
      scope === 'all' ? response({ layout: SKU_LAYOUT }) : response({ layout: SKU_LAYOUT, inherited: SKU_LAYOUT }),
    )
    saveAttributeLayoutMock.mockResolvedValue(null)
    render(<ProductCategoryAttributeLayoutEditor categoryId={7} onCancel={onCancelMock} />, { wrapper: wrapper() })
    await selectScope('Edit')

    fireEvent.click(await screen.findByRole('button', { name: 'Back to all modes' }))
    fireEvent.click(await screen.findByRole('button', { name: 'Remove' }))

    await waitFor(() => expect(saveAttributeLayoutMock).toHaveBeenCalledWith(7, 'product', 'edit', { sections: [] }))
    expect(await screen.findByText('This mode uses the “All modes” layout.')).toBeInTheDocument()
  })

  it('Cancel discards the draft and closes the host Sheet without saving', async () => {
    render(<ProductCategoryAttributeLayoutEditor categoryId={7} onCancel={onCancelMock} />, { wrapper: wrapper() })
    await waitFor(() => expect(fetchAttributeLayoutMock).toHaveBeenCalled())

    fireEvent.click(await screen.findByRole('button', { name: 'Cancel' }))

    expect(onCancelMock).toHaveBeenCalledTimes(1)
    expect(saveAttributeLayoutMock).not.toHaveBeenCalled()
  })
})
