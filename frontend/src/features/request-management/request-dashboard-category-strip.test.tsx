import { useState, type ReactNode } from 'react'
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { Tabs } from '@/components/ui/tabs'
import { fetchCategoryTabPreferences, saveCategoryTabPreferences } from '@/features/category-tabs/api'
import type { CategoryTabPreferences } from '@/features/category-tabs/types'
import type { RequestDashboardCategory } from '@/features/request-management/dashboard-api'
import type { RequestReportCategory } from '@/features/request-management/report-api'
import { DashboardCategoryStrip } from '@/features/request-management/request-dashboard-category-strip'
import { OVERVIEW_TAB } from '@/features/request-management/use-request-dashboard-tab'

/**
 * The statistics tab strip laid out like Gestione Richieste's (user directive
 * 2026-10-05), with its favourites and "parent categories" switch (spec 0385).
 * jsdom has no layout, so every measured box is stubbed at 100px and the strip
 * at 400px: "Overview" plus two categories fit next to the menu button, the
 * other three fold into it. Keys are category ids, as the server sends them.
 */

vi.mock('@/features/category-tabs/api', () => ({
  fetchCategoryTabPreferences: vi.fn(),
  saveCategoryTabPreferences: vi.fn(),
}))

const fetchMock = vi.mocked(fetchCategoryTabPreferences)
const saveMock = vi.mocked(saveCategoryTabPreferences)

const STATISTICS_BASE_PATH = '/request-management/report'

/** "Yisu" is a subcategory of "GOL": the "parent categories" switch hides it. */
const BRANCHES: RequestReportCategory[] = [
  { key: '1', label: 'GOL', depth: 0, parent_key: null },
  { key: '2', label: 'DIL', depth: 0, parent_key: null },
  { key: '3', label: 'APL', depth: 0, parent_key: null },
  { key: '4', label: 'Yisu', depth: 1, parent_key: '1' },
  { key: '5', label: 'Consulenza', depth: 0, parent_key: null },
]

const CATEGORIES: RequestDashboardCategory[] = BRANCHES.map(({ key, label }) => ({ key, label, summary: [], charts: [] }))

function preferences(favoriteIds: number[], showOnlyFavorites = false): CategoryTabPreferences {
  return { favorite_category_ids: favoriteIds, show_only_favorites: showOnlyFavorites, is_default: false }
}

function Harness({ initial = OVERVIEW_TAB }: { initial?: string }) {
  const [tab, setTab] = useState(initial)

  return (
    <Tabs value={tab} onValueChange={setTab}>
      <DashboardCategoryStrip categories={CATEGORIES} branches={BRANCHES} value={tab} onSelect={setTab} />
    </Tabs>
  )
}

function renderStrip(initial?: string) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  const wrapper = ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
  return render(<Harness initial={initial} />, { wrapper })
}

function inlineTabs() {
  return within(screen.getByRole('tablist', { name: 'Statistics sections' }))
    .getAllByRole('tab')
    .map((tab) => tab.textContent)
}

function openMenu(name: string | RegExp = /More|Categories and favorites/) {
  fireEvent.click(screen.getByRole('button', { name }))
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  window.localStorage.clear()
  fetchMock.mockReset().mockResolvedValue(preferences([]))
  saveMock.mockReset().mockImplementation(async (_path, next) => ({ ...next, is_default: false }))
  vi.spyOn(HTMLElement.prototype, 'offsetWidth', 'get').mockReturnValue(100)
  vi.spyOn(Element.prototype, 'clientWidth', 'get').mockReturnValue(400)
})

afterEach(() => {
  vi.restoreAllMocks()
})

describe('DashboardCategoryStrip', () => {
  it('keeps the tabs that fit inline and folds the rest into "More (N)"', () => {
    renderStrip()

    expect(inlineTabs()).toEqual(['Overview', 'GOL', 'DIL'])
    expect(screen.getByRole('button', { name: 'More (3)' })).toBeInTheDocument()
  })

  it('opens a folded category from the menu, which then stays inline and selected', () => {
    renderStrip()

    openMenu('More (3)')
    fireEvent.change(screen.getByRole('textbox', { name: 'Search category…' }), { target: { value: 'consu' } })
    const rows = within(screen.getByRole('list', { name: 'Product categories' })).getAllByRole('listitem')
    expect(rows).toHaveLength(1)
    fireEvent.click(within(rows[0]).getByRole('button', { name: 'Consulenza' }))

    expect(inlineTabs()).toEqual(['Overview', 'GOL', 'Consulenza'])
    expect(screen.getByRole('tab', { name: 'Consulenza' })).toHaveAttribute('aria-selected', 'true')
  })

  // REQUIREMENT CHANGED (spec 0385): the menu also manages the favourites, so
  // it stays on the strip as a star even when every tab fits.
  it('keeps the menu as a star button when every tab fits', () => {
    vi.spyOn(Element.prototype, 'clientWidth', 'get').mockReturnValue(2000)

    renderStrip()

    expect(inlineTabs()).toEqual(['Overview', 'GOL', 'DIL', 'APL', 'Yisu', 'Consulenza'])
    expect(screen.queryByRole('button', { name: /More/ })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Categories and favorites' })).toBeInTheDocument()
  })

  it('reads the favourites from the statistics endpoint and shows them first (AC-006)', async () => {
    fetchMock.mockResolvedValue(preferences([5]))

    renderStrip()

    await waitFor(() => expect(inlineTabs()).toEqual(['Overview', 'Consulenza', 'GOL']))
    expect(fetchMock).toHaveBeenCalledWith(STATISTICS_BASE_PATH)
  })

  it('saves a starred category by its category id (AC-007)', async () => {
    renderStrip()
    await waitFor(() => expect(fetchMock).toHaveBeenCalled())

    openMenu('More (3)')
    fireEvent.click(screen.getByRole('button', { name: 'Add APL to favorites' }))

    await waitFor(() =>
      expect(saveMock).toHaveBeenCalledWith(STATISTICS_BASE_PATH, { favorite_category_ids: [3], show_only_favorites: false }),
    )
    expect(screen.getByRole('button', { name: 'Remove APL from favorites' })).toHaveAttribute('aria-pressed', 'true')
  })

  it('shows only the favourites when the switch is on (AC-008)', async () => {
    fetchMock.mockResolvedValue(preferences([3, 5], true))

    renderStrip()

    await waitFor(() => expect(inlineTabs()).toEqual(['Overview', 'APL', 'Consulenza']))
    openMenu('More (3)')
    expect(screen.getByRole('switch', { name: 'Show favorites only' })).toBeChecked()
  })

  it('hides the subcategories with "Parent categories" and remembers the choice (AC-009)', async () => {
    vi.spyOn(Element.prototype, 'clientWidth', 'get').mockReturnValue(2000)
    renderStrip()

    openMenu('Categories and favorites')
    const levelSwitch = screen.getByRole('switch', { name: 'Parent categories' })
    expect(levelSwitch).not.toBeChecked()
    fireEvent.click(levelSwitch)

    await waitFor(() => expect(inlineTabs()).toEqual(['Overview', 'GOL', 'DIL', 'APL', 'Consulenza']))
    expect(window.localStorage.getItem('request-statistics.category-tab-level')).toBe('root')
  })

  it('keeps an open subcategory on the strip at the parent level (AC-009)', () => {
    vi.spyOn(Element.prototype, 'clientWidth', 'get').mockReturnValue(2000)
    window.localStorage.setItem('request-statistics.category-tab-level', 'root')

    renderStrip('4')

    expect(inlineTabs()).toEqual(['Overview', 'GOL', 'DIL', 'APL', 'Consulenza', 'Yisu'])
    expect(screen.getByRole('tab', { name: 'Yisu' })).toHaveAttribute('aria-selected', 'true')
  })

  it('"Deselect all" keeps the favourites of categories the strip does not list (AC-010)', async () => {
    fetchMock.mockResolvedValue(preferences([3, 99]))
    renderStrip()
    await waitFor(() => expect(inlineTabs()).toEqual(['Overview', 'APL', 'GOL']))

    openMenu('More (3)')
    fireEvent.click(screen.getByRole('button', { name: 'Deselect all' }))

    await waitFor(() =>
      expect(saveMock).toHaveBeenCalledWith(STATISTICS_BASE_PATH, { favorite_category_ids: [99], show_only_favorites: false }),
    )
  })
})
