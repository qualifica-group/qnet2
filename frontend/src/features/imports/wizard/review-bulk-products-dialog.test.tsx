import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import '@/features/imports/wizard/i18n'
import { ReviewBulkProductsDialog } from '@/features/imports/wizard/review-bulk-products-dialog'

/**
 * Bulk "Assegna prodotti" popup (spec 0094 bulk delta): assign-only, no
 * "use default"/"no products" shortcuts (those stay row-scoped, see
 * `review-products-editor.test.tsx`), Applica disabled while the picker is
 * empty. The real `AsyncPaginatedMultiSelect` is mocked to a plain toggle
 * button, mirroring `review-products-editor.test.tsx`.
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

beforeEach(() => {
  vi.clearAllMocks()
})

function pickProduct() {
  fireEvent.click(screen.getByRole('button', { name: 'Assign products' }))
}

describe('ReviewBulkProductsDialog', () => {
  it('shows the selection count in the description and disables Applica while empty', () => {
    render(
      <ReviewBulkProductsDialog
        open
        onOpenChange={vi.fn()}
        selectionCount={3}
        campaignCategoryIds={[]}
        onApply={vi.fn().mockResolvedValue(undefined)}
      />,
    )
    expect(screen.getByText('Assign products of interest to 3 selected row(s).')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Apply' })).toBeDisabled()
  })

  it('enables Applica once a product is picked', () => {
    render(
      <ReviewBulkProductsDialog
        open
        onOpenChange={vi.fn()}
        selectionCount={2}
        campaignCategoryIds={[]}
        onApply={vi.fn().mockResolvedValue(undefined)}
      />,
    )
    pickProduct()
    expect(screen.getByRole('button', { name: 'Apply' })).not.toBeDisabled()
  })

  it('calls onApply with the picked ids and closes on success', async () => {
    const onApply = vi.fn().mockResolvedValue(undefined)
    const onOpenChange = vi.fn()
    render(
      <ReviewBulkProductsDialog
        open
        onOpenChange={onOpenChange}
        selectionCount={2}
        campaignCategoryIds={[3, 4]}
        onApply={onApply}
      />,
    )
    pickProduct()
    fireEvent.click(screen.getByRole('button', { name: 'Apply' }))

    await waitFor(() => expect(onApply).toHaveBeenCalledWith([PICKED_PRODUCT_ID]))
    await waitFor(() => expect(onOpenChange).toHaveBeenCalledWith(false))
  })

  it('Annulla closes the popup without calling onApply', () => {
    const onApply = vi.fn()
    const onOpenChange = vi.fn()
    render(
      <ReviewBulkProductsDialog
        open
        onOpenChange={onOpenChange}
        selectionCount={2}
        campaignCategoryIds={[]}
        onApply={onApply}
      />,
    )
    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))

    expect(onApply).not.toHaveBeenCalled()
    expect(onOpenChange).toHaveBeenCalledWith(false)
  })

  it('shows an accessible error and keeps the popup open when the PATCH fails', async () => {
    const onApply = vi.fn().mockRejectedValue({ isAxiosError: true, response: { status: 422 } })
    const onOpenChange = vi.fn()
    render(
      <ReviewBulkProductsDialog
        open
        onOpenChange={onOpenChange}
        selectionCount={2}
        campaignCategoryIds={[]}
        onApply={onApply}
      />,
    )
    pickProduct()
    fireEvent.click(screen.getByRole('button', { name: 'Apply' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Some values are not valid. Please check and try again.')
    expect(onOpenChange).not.toHaveBeenCalledWith(false)
  })
})
