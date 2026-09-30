import { describe, expect, it } from 'vitest'
import { filterCategoriesByName, fitCategoryTabs } from '@/features/request-management/category-tab-fit'

const IDS = [1, 2, 3, 4, 5]
const WIDTHS = [100, 100, 100, 100, 100]

function fit(availableWidth: number, selectedCategoryId: number | null = null) {
  return fitCategoryTabs({
    categoryIds: IDS,
    tabWidths: WIDTHS,
    availableWidth,
    moreWidth: 80,
    gap: 4,
    selectedCategoryId,
  })
}

describe('fitCategoryTabs', () => {
  it('keeps every tab inline, with no room reserved for the menu, when they all fit', () => {
    // 5 tabs x (4 gap + 100) = 520
    expect(fit(520)).toEqual(IDS)
  })

  it('keeps the leading tabs that fit next to the "More" button and folds the rest', () => {
    // budget 519 - 4 - 80 = 435 → 4 tabs (416) fit, a 5th (520) does not
    expect(fit(519)).toEqual([1, 2, 3, 4])
    // budget 300 - 84 = 216 → 2 tabs (208)
    expect(fit(300)).toEqual([1, 2])
  })

  it('pulls a folded selected category inline in place of the trailing tabs', () => {
    // budget 216: [1, 2] fit, selected 5 needs 104 more → only [1] stays
    expect(fit(300, 5)).toEqual([1, 5])
  })

  it('leaves the layout alone when the selected category is already inline', () => {
    expect(fit(300, 2)).toEqual([1, 2])
  })

  it('still shows the selected category alone when not even one tab fits', () => {
    expect(fit(50, 4)).toEqual([4])
    expect(fit(50)).toEqual([])
  })
})

describe('filterCategoriesByName', () => {
  const categories = [{ name: 'GOL - Lombardia' }, { name: 'Formazione Città' }, { name: 'ISO 9001' }]

  it('keeps every category for a blank term', () => {
    expect(filterCategoriesByName(categories, '  ')).toEqual(categories)
  })

  it('matches a substring ignoring case and accents', () => {
    expect(filterCategoriesByName(categories, 'lomb')).toEqual([{ name: 'GOL - Lombardia' }])
    expect(filterCategoriesByName(categories, 'CITTA')).toEqual([{ name: 'Formazione Città' }])
  })
})
