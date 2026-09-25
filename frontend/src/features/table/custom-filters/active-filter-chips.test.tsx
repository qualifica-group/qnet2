import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, within } from '@testing-library/react'
import i18n from '@/i18n'
import { ActiveFilterChips } from '@/features/table/custom-filters/active-filter-chips'
import type { FilterChip } from '@/features/table/custom-filters/use-active-filter-chips'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('ActiveFilterChips', () => {
  it('renders nothing when there is no active filter', () => {
    const { container } = render(<ActiveFilterChips chips={[]} onClearAll={vi.fn()} />)
    expect(container).toBeEmptyDOMElement()
  })

  it('renders one listitem per chip and a trailing "Azzera tutto"', () => {
    const chips: FilterChip[] = [
      { id: 'a', label: 'Email: acme', onRemove: vi.fn() },
      { id: 'b', label: 'Search: acme', onRemove: vi.fn() },
    ]
    render(<ActiveFilterChips chips={chips} onClearAll={vi.fn()} />)

    const list = screen.getByRole('list')
    expect(within(list).getAllByRole('listitem')).toHaveLength(2)
    expect(screen.getByText('Email: acme')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Clear all' })).toBeInTheDocument()
  })

  it("a chip's x removes only that chip", () => {
    const removeA = vi.fn()
    const removeB = vi.fn()
    const chips: FilterChip[] = [
      { id: 'a', label: 'Email: acme', onRemove: removeA },
      { id: 'b', label: 'Search: acme', onRemove: removeB },
    ]
    render(<ActiveFilterChips chips={chips} onClearAll={vi.fn()} />)

    fireEvent.click(screen.getByRole('button', { name: 'Remove Email: acme' }))

    expect(removeA).toHaveBeenCalledTimes(1)
    expect(removeB).not.toHaveBeenCalled()
  })

  it('Clear all calls onClearAll', () => {
    const onClearAll = vi.fn()
    render(
      <ActiveFilterChips chips={[{ id: 'a', label: 'x', onRemove: vi.fn() }]} onClearAll={onClearAll} />,
    )

    fireEvent.click(screen.getByRole('button', { name: 'Clear all' }))

    expect(onClearAll).toHaveBeenCalledTimes(1)
  })
})
