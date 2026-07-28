import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { RequestManagementCategoryTabs } from '@/features/request-management/request-management-category-tabs'
import type { RequestManagementProductCategory } from '@/features/request-management/types'

/**
 * Spec 0064 AC-019: "Tutte" always first, plus one trigger per Product
 * Category the endpoint actually returned, each carrying its request count.
 */

const CATEGORIES: RequestManagementProductCategory[] = [
  { id: 12, name: 'GOL - Lombardia', requests_count: 128 },
  { id: 7, name: 'Formazione', requests_count: 4 },
]

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('RequestManagementCategoryTabs', () => {
  it('renders "All" first and selected by default, plus one tab per category with its count', () => {
    render(
      <RequestManagementCategoryTabs categories={CATEGORIES} selectedCategoryId={null} onSelect={vi.fn()} />,
    )

    const tabs = screen.getAllByRole('tab')
    expect(tabs[0]).toHaveTextContent('All')
    expect(tabs[0]).toHaveAttribute('aria-selected', 'true')

    expect(screen.getByRole('tab', { name: /GOL - Lombardia/ })).toHaveTextContent('128')
    expect(screen.getByRole('tab', { name: /Formazione/ })).toHaveTextContent('4')
  })

  it('renders no category tab when the endpoint returns none, only "All"', () => {
    render(<RequestManagementCategoryTabs categories={[]} selectedCategoryId={null} onSelect={vi.fn()} />)

    expect(screen.getAllByRole('tab')).toHaveLength(1)
  })

  it('marks the selected category tab active, not "All"', () => {
    render(
      <RequestManagementCategoryTabs categories={CATEGORIES} selectedCategoryId={12} onSelect={vi.fn()} />,
    )

    expect(screen.getByRole('tab', { name: 'All' })).toHaveAttribute('aria-selected', 'false')
    expect(screen.getByRole('tab', { name: /GOL - Lombardia/ })).toHaveAttribute('aria-selected', 'true')
  })

  it('reports the picked category id, and null when "All" is picked back', () => {
    const onSelect = vi.fn()
    render(
      <RequestManagementCategoryTabs categories={CATEGORIES} selectedCategoryId={12} onSelect={onSelect} />,
    )

    fireEvent.mouseDown(screen.getByRole('tab', { name: /Formazione/ }))
    expect(onSelect).toHaveBeenCalledWith(7)

    fireEvent.mouseDown(screen.getByRole('tab', { name: 'All' }))
    expect(onSelect).toHaveBeenCalledWith(null)
  })
})
