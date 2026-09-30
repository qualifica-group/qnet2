import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, within } from '@testing-library/react'
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

  it('shows no "More" menu while every category fits', () => {
    render(
      <RequestManagementCategoryTabs categories={CATEGORIES} selectedCategoryId={null} onSelect={vi.fn()} />,
    )

    expect(screen.queryByRole('button', { name: /More/ })).not.toBeInTheDocument()
  })
})

/**
 * Overflow (priority+): jsdom has no layout, so every measured box is stubbed
 * at 100px and the strip at 400px — "All" plus two categories fit next to the
 * "More" button, the other three fold into it.
 */
describe('RequestManagementCategoryTabs overflow', () => {
  const MANY: RequestManagementProductCategory[] = [
    { id: 1, name: 'ISO 9001', requests_count: 10 },
    { id: 2, name: 'ISO 14001', requests_count: 9 },
    { id: 3, name: 'GOL - Lombardia', requests_count: 8 },
    { id: 4, name: 'Formazione Città', requests_count: 7 },
    { id: 5, name: 'Finanza agevolata', requests_count: 6 },
  ]

  beforeEach(() => {
    vi.spyOn(HTMLElement.prototype, 'offsetWidth', 'get').mockReturnValue(100)
    vi.spyOn(Element.prototype, 'clientWidth', 'get').mockReturnValue(400)
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('keeps the tabs that fit inline and folds the rest into "More (N)"', () => {
    render(<RequestManagementCategoryTabs categories={MANY} selectedCategoryId={null} onSelect={vi.fn()} />)

    expect(screen.getAllByRole('tab').map((tab) => tab.textContent)).toEqual(['All', 'ISO 900110', 'ISO 140019'])
    expect(screen.getByRole('button', { name: 'More (3)' })).toBeInTheDocument()
  })

  it('keeps a folded selected category inline, in place of the last tab that fit', () => {
    render(<RequestManagementCategoryTabs categories={MANY} selectedCategoryId={5} onSelect={vi.fn()} />)

    expect(screen.getAllByRole('tab').map((tab) => tab.textContent)).toEqual([
      'All',
      'ISO 900110',
      'Finanza agevolata6',
    ])
    expect(screen.getByRole('tab', { name: /Finanza agevolata/ })).toHaveAttribute('aria-selected', 'true')
  })

  it('lists every category with its count in the menu and filters them as the operator types', () => {
    render(<RequestManagementCategoryTabs categories={MANY} selectedCategoryId={null} onSelect={vi.fn()} />)

    fireEvent.click(screen.getByRole('button', { name: 'More (3)' }))
    expect(within(screen.getByRole('listbox')).getAllByRole('option')).toHaveLength(5)

    fireEvent.change(screen.getByRole('combobox', { name: 'Search category…' }), { target: { value: 'citta' } })
    const options = within(screen.getByRole('listbox')).getAllByRole('option')
    expect(options).toHaveLength(1)
    expect(options[0]).toHaveTextContent('Formazione Città7')

    fireEvent.change(screen.getByRole('combobox'), { target: { value: 'zzz' } })
    expect(screen.getByText('No category found.')).toBeInTheDocument()
  })

  it('picks a category from the menu by click', () => {
    const onSelect = vi.fn()
    render(<RequestManagementCategoryTabs categories={MANY} selectedCategoryId={null} onSelect={onSelect} />)

    fireEvent.click(screen.getByRole('button', { name: 'More (3)' }))
    fireEvent.click(screen.getByRole('option', { name: /GOL - Lombardia/ }))

    expect(onSelect).toHaveBeenCalledWith(3)
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument()
  })

  it('picks a category from the menu with the arrow keys and Enter, starting on the current one', () => {
    const onSelect = vi.fn()
    render(<RequestManagementCategoryTabs categories={MANY} selectedCategoryId={2} onSelect={onSelect} />)

    fireEvent.click(screen.getByRole('button', { name: 'More (3)' }))
    const search = screen.getByRole('combobox')
    expect(search).toHaveAttribute('aria-activedescendant', screen.getByRole('option', { name: /ISO 14001/ }).id)

    fireEvent.keyDown(search, { key: 'ArrowDown' })
    fireEvent.keyDown(search, { key: 'ArrowDown' })
    fireEvent.keyDown(search, { key: 'Enter' })

    expect(onSelect).toHaveBeenCalledWith(4)
  })
})
