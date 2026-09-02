import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import type { ICellRendererParams, IRowNode } from 'ag-grid-community'
import i18n from '@/i18n'
import '@/features/imports/wizard/i18n'
import {
  ReviewProductsCell,
  type ReviewProductsCellParams,
  type ReviewProductsGridContext,
} from '@/features/imports/wizard/review-products-editor'
import type { ImportRunRowItem } from '@/features/imports/wizard/types'

/**
 * Spec 0094 AC-055: the products column shows the row's own override, the
 * run's "uses default" hint (only when a global default is configured), or
 * "no products"; a popup applies the override via `context.onApplyProducts`,
 * distinguishing `null` (revert to the run's global default) from `[]` (an
 * explicit "no products on this row") — two states, not one.
 */

const PICKED_PRODUCT_ID = 5

vi.mock('@/components/ui/async-paginated-multi-select', () => ({
  AsyncPaginatedMultiSelect: ({
    value,
    onChange,
    labels,
  }: {
    value: number[]
    onChange: (value: number[]) => void
    labels: { triggerLabel: string }
  }) => (
    <button
      type="button"
      aria-label={labels.triggerLabel}
      onClick={() =>
        onChange(
          value.includes(PICKED_PRODUCT_ID)
            ? value.filter((id) => id !== PICKED_PRODUCT_ID)
            : [...value, PICKED_PRODUCT_ID],
        )
      }
    >
      {value.length > 0 ? value.join(',') : 'none'}
    </button>
  ),
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

function rowItem(overrides: Partial<ImportRunRowItem> = {}): ImportRunRowItem {
  return {
    id: 10,
    row_number: 1,
    status: 'valid',
    is_edited: false,
    duplicate_of_id: null,
    operator_id: null,
    operator: null,
    operational_site_id: null,
    operational_site: null,
    product_ids: null,
    products: [],
    values: {},
    messages: [],
    ...overrides,
  }
}

beforeEach(() => {
  vi.clearAllMocks()
})

function renderCell(overrides: Partial<ReviewProductsCellParams> = {}) {
  const node = { setData: vi.fn() } as unknown as IRowNode<ImportRunRowItem>
  const onApplyProducts = vi.fn().mockResolvedValue(undefined)
  const context: ReviewProductsGridContext = {
    onApplyProducts,
    hasGlobalDefaultProducts: true,
    campaignCategoryIds: [3, 4],
  }
  render(
    <ReviewProductsCell
      {...({
        data: rowItem(),
        node,
        context,
        ...overrides,
      } as ReviewProductsCellParams & ICellRendererParams)}
    />,
  )
  return { node, onApplyProducts }
}

describe('ReviewProductsCell — display states', () => {
  it('shows the "uses default" hint when the row inherits (product_ids: null) and the run has a global default', () => {
    renderCell()
    expect(screen.getByRole('button', { name: 'Edit products of interest' })).toHaveTextContent('Default products')
  })

  it('shows an empty placeholder, not the default hint, when the run has no global default', () => {
    renderCell({
      context: { onApplyProducts: vi.fn(), hasGlobalDefaultProducts: false, campaignCategoryIds: [] },
    })
    expect(screen.getByRole('button', { name: 'Edit products of interest' })).toHaveTextContent('—')
  })

  it('shows the distinct "no products" state when the row overrides to an explicit empty array', () => {
    renderCell({ data: rowItem({ product_ids: [] }) })
    expect(screen.getByRole('button', { name: 'Edit products of interest' })).toHaveTextContent('No products')
  })

  it("shows the row's own hydrated product labels when overridden", () => {
    renderCell({
      data: rowItem({ product_ids: [7, 8], products: [{ id: 7, label: 'Widget' }, { id: 8, label: 'Gadget' }] }),
    })
    expect(screen.getByRole('button', { name: 'Edit products of interest' })).toHaveTextContent('Widget, Gadget')
  })

  it('renders plain text with no popup affordance in readOnly mode', () => {
    renderCell({ readOnly: true })
    expect(screen.getByText('Default products')).toBeInTheDocument()
    expect(screen.queryByRole('button')).not.toBeInTheDocument()
  })
})

describe('ReviewProductsCell — popup apply (null vs [] distinction)', () => {
  it('sends a single PATCH via context.onApplyProducts with the picked ids, then closes', async () => {
    const { node, onApplyProducts } = renderCell()

    fireEvent.click(screen.getByRole('button', { name: 'Edit products of interest' }))
    fireEvent.click(screen.getByRole('button', { name: 'Products of interest' }))
    fireEvent.click(screen.getByRole('button', { name: 'Apply' }))

    await waitFor(() => expect(onApplyProducts).toHaveBeenCalledWith(rowItem(), [PICKED_PRODUCT_ID], node))
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
  })

  it('"Use default" reverts an override to `null` (inherit the global default) — not `[]`', async () => {
    const overriddenRow = rowItem({ product_ids: [7], products: [{ id: 7, label: 'Widget' }] })
    const { node, onApplyProducts } = renderCell({ data: overriddenRow })

    fireEvent.click(screen.getByRole('button', { name: 'Edit products of interest' }))
    fireEvent.click(screen.getByRole('button', { name: 'Use default' }))
    fireEvent.click(screen.getByRole('button', { name: 'Apply' }))

    await waitFor(() => expect(onApplyProducts).toHaveBeenCalledWith(overriddenRow, null, node))
  })

  it('"No products on this row" sends an explicit `[]` — not `null` — distinct from "Use default"', async () => {
    const overriddenRow = rowItem({ product_ids: [7], products: [{ id: 7, label: 'Widget' }] })
    const { node, onApplyProducts } = renderCell({ data: overriddenRow })

    fireEvent.click(screen.getByRole('button', { name: 'Edit products of interest' }))
    fireEvent.click(screen.getByRole('button', { name: 'No products on this row' }))
    fireEvent.click(screen.getByRole('button', { name: 'Apply' }))

    await waitFor(() => expect(onApplyProducts).toHaveBeenCalledWith(overriddenRow, [], node))
  })

  it('disables "Use default" once already null and "No products on this row" once already []', () => {
    renderCell({ data: rowItem({ product_ids: null }) })
    fireEvent.click(screen.getByRole('button', { name: 'Edit products of interest' }))

    expect(screen.getByRole('button', { name: 'Use default' })).toBeDisabled()
    expect(screen.getByRole('button', { name: 'No products on this row' })).not.toBeDisabled()
  })

  it('Annulla closes the popup without calling onApplyProducts', () => {
    const { onApplyProducts } = renderCell()

    fireEvent.click(screen.getByRole('button', { name: 'Edit products of interest' }))
    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))

    expect(onApplyProducts).not.toHaveBeenCalled()
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('shows an accessible error and keeps the popup open when the PATCH fails', async () => {
    const onApplyProducts = vi.fn().mockRejectedValue({ isAxiosError: true, response: { status: 422 } })
    const node = { setData: vi.fn() } as unknown as IRowNode<ImportRunRowItem>
    render(
      <ReviewProductsCell
        {...({
          data: rowItem(),
          node,
          context: { onApplyProducts, hasGlobalDefaultProducts: true, campaignCategoryIds: [] },
        } as ReviewProductsCellParams & ICellRendererParams)}
      />,
    )

    fireEvent.click(screen.getByRole('button', { name: 'Edit products of interest' }))
    fireEvent.click(screen.getByRole('button', { name: 'Apply' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Some values are not valid. Please check and try again.')
    expect(screen.getByRole('dialog')).toBeInTheDocument()
    expect(node.setData).not.toHaveBeenCalled()
  })
})
