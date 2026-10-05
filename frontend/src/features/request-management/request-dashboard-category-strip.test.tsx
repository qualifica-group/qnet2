import { useState } from 'react'
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, within } from '@testing-library/react'
import i18n from '@/i18n'
import { Tabs } from '@/components/ui/tabs'
import type { RequestDashboardCategory } from '@/features/request-management/dashboard-api'
import { DashboardCategoryStrip } from '@/features/request-management/request-dashboard-category-strip'
import { OVERVIEW_TAB } from '@/features/request-management/use-request-dashboard-tab'

/**
 * The statistics tab strip laid out like Gestione Richieste's (user directive
 * 2026-10-05). jsdom has no layout, so every measured box is stubbed at 100px
 * and the strip at 400px: "Overview" plus two categories fit next to the
 * "More" button, the other three fold into it.
 */

const CATEGORIES: RequestDashboardCategory[] = ['GOL', 'DIL', 'APL', 'Yisu', 'Consulenza'].map((label) => ({
  key: label.toLowerCase(),
  label,
  summary: [],
  charts: [],
}))

function Harness({ initial = OVERVIEW_TAB }: { initial?: string }) {
  const [tab, setTab] = useState(initial)

  return (
    <Tabs value={tab} onValueChange={setTab}>
      <DashboardCategoryStrip categories={CATEGORIES} value={tab} onSelect={setTab} />
    </Tabs>
  )
}

function inlineTabs() {
  return within(screen.getByRole('tablist', { name: 'Statistics sections' }))
    .getAllByRole('tab')
    .map((tab) => tab.textContent)
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.spyOn(HTMLElement.prototype, 'offsetWidth', 'get').mockReturnValue(100)
  vi.spyOn(Element.prototype, 'clientWidth', 'get').mockReturnValue(400)
})

afterEach(() => {
  vi.restoreAllMocks()
})

describe('DashboardCategoryStrip', () => {
  it('keeps the tabs that fit inline and folds the rest into "More (N)"', () => {
    render(<Harness />)

    expect(inlineTabs()).toEqual(['Overview', 'GOL', 'DIL'])
    expect(screen.getByRole('button', { name: 'More (3)' })).toBeInTheDocument()
  })

  it('opens a folded category from the menu, which then stays inline and selected', () => {
    render(<Harness />)

    fireEvent.click(screen.getByRole('button', { name: 'More (3)' }))
    fireEvent.change(screen.getByRole('textbox', { name: 'Search category…' }), { target: { value: 'consu' } })
    const rows = within(screen.getByRole('list', { name: 'Product categories' })).getAllByRole('listitem')
    expect(rows).toHaveLength(1)
    fireEvent.click(within(rows[0]).getByRole('button', { name: 'Consulenza' }))

    expect(inlineTabs()).toEqual(['Overview', 'GOL', 'Consulenza'])
    expect(screen.getByRole('tab', { name: 'Consulenza' })).toHaveAttribute('aria-selected', 'true')
  })

  it('shows no menu when every tab fits', () => {
    vi.spyOn(Element.prototype, 'clientWidth', 'get').mockReturnValue(2000)

    render(<Harness />)

    expect(inlineTabs()).toEqual(['Overview', 'GOL', 'DIL', 'APL', 'Yisu', 'Consulenza'])
    expect(screen.queryByRole('button', { name: /More/ })).not.toBeInTheDocument()
  })
})
