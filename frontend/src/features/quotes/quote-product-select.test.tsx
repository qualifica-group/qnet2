import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { QuoteProductSelect } from '@/features/quotes/quote-product-select'

vi.mock('@/features/for-select/use-for-select', async () => {
  const actual = await vi.importActual<
    typeof import('@/features/for-select/use-for-select')
  >('@/features/for-select/use-for-select')
  return {
    flattenForSelectPages: actual.flattenForSelectPages,
    useForSelect: () => ({
      data: { pages: [{ items: [{ id: 1, label: "[LM-56] Scienze dell'Economia 1°ANNO" }] }] },
      isPending: false,
      isError: false,
      fetchNextPage: vi.fn(),
      hasNextPage: false,
      isFetchingNextPage: false,
      refetch: vi.fn(),
    }),
    useForSelectLabels: () => new Map(),
  }
})

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('QuoteProductSelect popup width', () => {
  it('opens a popup wider than the trigger, capped by the available viewport width', () => {
    render(
      <QuoteProductSelect value={null} onChange={vi.fn()} usage="SALE" triggerLabel="Product" />,
    )

    fireEvent.click(screen.getByRole('combobox', { name: 'Product' }))

    const popup = screen.getByRole('listbox').closest('[data-radix-popper-content-wrapper] > *')
    expect(popup).toHaveClass('w-[min(36rem,var(--radix-popover-content-available-width))]')
    expect(popup).toHaveClass('min-w-(--radix-popover-trigger-width)')
    expect(popup).not.toHaveClass('w-(--radix-popover-trigger-width)')
  })
})
