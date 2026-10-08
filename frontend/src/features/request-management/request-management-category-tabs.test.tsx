import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, within } from '@testing-library/react'
import type { ComponentProps } from 'react'
import i18n from '@/i18n'
import { RequestManagementCategoryTabs } from '@/features/request-management/request-management-category-tabs'
import type { RequestManagementProductCategory } from '@/features/request-management/types'

/**
 * Spec 0064 AC-019: "Tutte" always first, plus one trigger per Product
 * Category the endpoint actually returned, each carrying its request count.
 * Spec 0184: favourites first, "only favourites" switch, star in the menu.
 */

const CATEGORIES: RequestManagementProductCategory[] = [
  { id: 12, name: 'GOL - Lombardia', requests_count: 128 },
  { id: 7, name: 'Formazione', requests_count: 4 },
]

const MANY: RequestManagementProductCategory[] = [
  { id: 1, name: 'ISO 9001', requests_count: 10 },
  { id: 2, name: 'ISO 14001', requests_count: 9 },
  { id: 3, name: 'GOL - Lombardia', requests_count: 8 },
  { id: 4, name: 'Formazione Città', requests_count: 7 },
  { id: 5, name: 'Finanza agevolata', requests_count: 6 },
]

type TabsProps = ComponentProps<typeof RequestManagementCategoryTabs>

function renderTabs(overrides: Partial<TabsProps> = {}) {
  const props: TabsProps = {
    categories: CATEGORIES,
    selectedCategoryId: null,
    onSelect: vi.fn(),
    favoriteCategoryIds: [],
    showOnlyFavorites: false,
    onToggleFavorite: vi.fn(),
    onShowOnlyFavoritesChange: vi.fn(),
    ...overrides,
  }
  const { rerender } = render(<RequestManagementCategoryTabs {...props} />)
  return { ...props, rerender: (next: Partial<TabsProps>) => rerender(<RequestManagementCategoryTabs {...props} {...next} />) }
}

const inlineTabs = () => screen.getAllByRole('tab').map((tab) => tab.textContent)
const categoryButton = (name: RegExp) => screen.getByRole('button', { name })

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('RequestManagementCategoryTabs', () => {
  it('renders "All" first and selected by default, plus one tab per category with its count', () => {
    renderTabs()

    const tabs = screen.getAllByRole('tab')
    expect(tabs[0]).toHaveTextContent('All')
    expect(tabs[0]).toHaveAttribute('aria-selected', 'true')

    expect(screen.getByRole('tab', { name: /GOL - Lombardia/ })).toHaveTextContent('128')
    expect(screen.getByRole('tab', { name: /Formazione/ })).toHaveTextContent('4')
  })

  it('renders no category tab when the endpoint returns none, only "All"', () => {
    renderTabs({ categories: [] })

    expect(screen.getAllByRole('tab')).toHaveLength(1)
  })

  it('marks the selected category tab active, not "All"', () => {
    renderTabs({ selectedCategoryId: 12 })

    expect(screen.getByRole('tab', { name: 'All' })).toHaveAttribute('aria-selected', 'false')
    expect(screen.getByRole('tab', { name: /GOL - Lombardia/ })).toHaveAttribute('aria-selected', 'true')
  })

  it('reports the picked category id, and null when "All" is picked back', () => {
    const { onSelect } = renderTabs({ selectedCategoryId: 12 })

    fireEvent.mouseDown(screen.getByRole('tab', { name: /Formazione/ }))
    expect(onSelect).toHaveBeenCalledWith(7)

    fireEvent.mouseDown(screen.getByRole('tab', { name: 'All' }))
    expect(onSelect).toHaveBeenCalledWith(null)
  })

  it('keeps the compact menu button while every category fits (spec 0184 AC-011)', () => {
    renderTabs()

    expect(screen.queryByRole('button', { name: /More/ })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Categories and favorites' })).toBeInTheDocument()
  })
})

describe('RequestManagementCategoryTabs favorites (spec 0184)', () => {
  it('shows the favorites before the other categories (AC-008)', () => {
    renderTabs({ categories: MANY, favoriteCategoryIds: [4, 2] })

    expect(inlineTabs()).toEqual([
      'All',
      'ISO 140019',
      'Formazione Città7',
      'ISO 900110',
      'GOL - Lombardia8',
      'Finanza agevolata6',
    ])
  })

  it('with "only favorites" keeps just the favorites and the selected category on the strip (AC-009)', () => {
    renderTabs({ categories: MANY, favoriteCategoryIds: [4, 2], showOnlyFavorites: true, selectedCategoryId: 5 })

    expect(inlineTabs()).toEqual(['All', 'ISO 140019', 'Formazione Città7', 'Finanza agevolata6'])
    expect(screen.getByRole('button', { name: 'More (2)' })).toBeInTheDocument()
  })

  it('lists favorites and other categories in their own groups in the menu', () => {
    renderTabs({ categories: MANY, favoriteCategoryIds: [4], showOnlyFavorites: true })

    fireEvent.click(screen.getByRole('button', { name: /More/ }))

    expect(within(screen.getByRole('list', { name: 'Favorites' })).getAllByRole('listitem')).toHaveLength(1)
    expect(within(screen.getByRole('list', { name: 'Other categories' })).getAllByRole('listitem')).toHaveLength(4)
  })

  it('toggles a favorite with the star without picking the category or closing the menu (AC-010)', () => {
    const { onToggleFavorite, onSelect } = renderTabs({ favoriteCategoryIds: [7] })

    fireEvent.click(screen.getByRole('button', { name: 'Categories and favorites' }))
    fireEvent.click(screen.getByRole('button', { name: 'Add GOL - Lombardia to favorites' }))
    expect(screen.getByRole('button', { name: 'Remove Formazione from favorites' })).toHaveAttribute(
      'aria-pressed',
      'true',
    )

    expect(onToggleFavorite).toHaveBeenCalledWith(12)
    expect(onSelect).not.toHaveBeenCalled()
    expect(screen.getByRole('textbox', { name: 'Search category…' })).toBeInTheDocument()
  })

  it('fills the star at once but keeps the row in place until the menu reopens', () => {
    const { rerender } = renderTabs()

    fireEvent.click(screen.getByRole('button', { name: 'Categories and favorites' }))
    rerender({ favoriteCategoryIds: [7] })

    expect(screen.getByRole('button', { name: 'Remove Formazione from favorites' })).toHaveAttribute(
      'aria-pressed',
      'true',
    )
    expect(screen.queryByRole('list', { name: 'Favorites' })).not.toBeInTheDocument()

    fireEvent.keyDown(screen.getByRole('textbox'), { key: 'Escape' })
    fireEvent.click(screen.getByRole('button', { name: 'Categories and favorites' }))
    expect(within(screen.getByRole('list', { name: 'Favorites' })).getByText('Formazione')).toBeInTheDocument()
  })

  it('reports the "only favorites" switch, disabled while there is no favorite', () => {
    const { onShowOnlyFavoritesChange } = renderTabs({ favoriteCategoryIds: [7] })

    fireEvent.click(screen.getByRole('button', { name: 'Categories and favorites' }))
    fireEvent.click(screen.getByRole('switch', { name: 'Show favorites only' }))

    expect(onShowOnlyFavoritesChange).toHaveBeenCalledWith(true)
  })

  it('disables the switch and explains the star when there is no favorite yet', () => {
    renderTabs()

    fireEvent.click(screen.getByRole('button', { name: 'Categories and favorites' }))

    expect(screen.getByRole('switch', { name: 'Show favorites only' })).toBeDisabled()
    expect(screen.getByText(/Tap the star/)).toBeInTheDocument()
  })
})

describe('RequestManagementCategoryTabs competence default (spec 0193)', () => {
  it('shows "All" plus the default favorites and tells in the menu that they are suggested (AC-008)', () => {
    renderTabs({
      categories: MANY,
      favoriteCategoryIds: [4, 2],
      showOnlyFavorites: true,
      favoritesAreDefault: true,
    })

    expect(inlineTabs()).toEqual(['All', 'ISO 140019', 'Formazione Città7'])
    fireEvent.click(screen.getByRole('button', { name: /More/ }))
    expect(screen.getByText(/suggested from the categories you are enabled for/)).toBeInTheDocument()
  })

  it('shows no suggestion notice once the favorites are the actor own', () => {
    renderTabs({ categories: MANY, favoriteCategoryIds: [4, 2], showOnlyFavorites: true })

    fireEvent.click(screen.getByRole('button', { name: /More/ }))

    expect(screen.queryByText(/suggested from the categories/)).not.toBeInTheDocument()
  })
})

/**
 * Overflow (priority+): jsdom has no layout, so every measured box is stubbed
 * at 100px and the strip at 400px — "All" plus two categories fit next to the
 * menu button, the other three fold into it.
 */
describe('RequestManagementCategoryTabs overflow', () => {
  beforeEach(() => {
    vi.spyOn(HTMLElement.prototype, 'offsetWidth', 'get').mockReturnValue(100)
    vi.spyOn(Element.prototype, 'clientWidth', 'get').mockReturnValue(400)
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('keeps the tabs that fit inline and folds the rest into "More (N)"', () => {
    renderTabs({ categories: MANY })

    expect(inlineTabs()).toEqual(['All', 'ISO 900110', 'ISO 140019'])
    expect(screen.getByRole('button', { name: 'More (3)' })).toBeInTheDocument()
  })

  it('keeps a folded selected category inline, in place of the last tab that fit', () => {
    renderTabs({ categories: MANY, selectedCategoryId: 5 })

    expect(inlineTabs()).toEqual(['All', 'ISO 900110', 'Finanza agevolata6'])
    expect(screen.getByRole('tab', { name: /Finanza agevolata/ })).toHaveAttribute('aria-selected', 'true')
  })

  it('lists every category with its count in the menu and filters them as the operator types', () => {
    renderTabs({ categories: MANY })

    fireEvent.click(screen.getByRole('button', { name: 'More (3)' }))
    expect(within(screen.getByRole('list', { name: 'Product categories' })).getAllByRole('listitem')).toHaveLength(5)

    fireEvent.change(screen.getByRole('textbox', { name: 'Search category…' }), { target: { value: 'citta' } })
    const rows = within(screen.getByRole('list')).getAllByRole('listitem')
    expect(rows).toHaveLength(1)
    expect(rows[0]).toHaveTextContent('Formazione Città7')

    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'zzz' } })
    expect(screen.getByText('No category found.')).toBeInTheDocument()
  })

  it('picks a category from the menu by click and closes it', () => {
    const { onSelect } = renderTabs({ categories: MANY })

    fireEvent.click(screen.getByRole('button', { name: 'More (3)' }))
    fireEvent.click(categoryButton(/^GOL - Lombardia/))

    expect(onSelect).toHaveBeenCalledWith(3)
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
  })

  it('moves from the search into the list with the arrow keys, and back up', () => {
    renderTabs({ categories: MANY })

    fireEvent.click(screen.getByRole('button', { name: 'More (3)' }))
    const search = screen.getByRole('textbox')

    fireEvent.keyDown(search, { key: 'ArrowDown' })
    expect(categoryButton(/^ISO 9001/)).toHaveFocus()

    fireEvent.keyDown(document.activeElement!, { key: 'ArrowDown' })
    expect(categoryButton(/^ISO 14001/)).toHaveFocus()

    fireEvent.keyDown(document.activeElement!, { key: 'ArrowUp' })
    fireEvent.keyDown(document.activeElement!, { key: 'ArrowUp' })
    expect(search).toHaveFocus()
  })

  it('picks the first match with Enter in the search', () => {
    const { onSelect } = renderTabs({ categories: MANY })

    fireEvent.click(screen.getByRole('button', { name: 'More (3)' }))
    const search = screen.getByRole('textbox')
    fireEvent.change(search, { target: { value: 'finanza' } })
    fireEvent.keyDown(search, { key: 'Enter' })

    expect(onSelect).toHaveBeenCalledWith(5)
  })
})
