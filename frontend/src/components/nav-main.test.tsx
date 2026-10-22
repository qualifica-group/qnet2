import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { NavMain } from '@/components/nav-main'
import { SidebarProvider } from '@/components/ui/sidebar'
import type { NavigationItem } from '@/features/navigation/types'

if (typeof window.matchMedia !== 'function') {
  window.matchMedia = ((query: string) => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: () => {},
    removeListener: () => {},
    addEventListener: () => {},
    removeEventListener: () => {},
    dispatchEvent: () => false,
  })) as unknown as typeof window.matchMedia
}

const ITEMS: NavigationItem[] = [
  { key: 'absences', label: 'navigation.absences', icon: null, route: '/absences', type: 'item', children: [] },
  { key: 'dashboard', label: 'navigation.dashboard', icon: null, route: '/dashboard', type: 'item', children: [] },
]

function renderNav(badges?: Parameters<typeof NavMain>[0]['badges']) {
  return render(
    <MemoryRouter>
      <SidebarProvider>
        <NavMain items={ITEMS} badges={badges} />
      </SidebarProvider>
    </MemoryRouter>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

describe('NavMain badges (spec 0304 AC-020)', () => {
  it('shows the count pill, with the exact count as accessible label, only on the entry that has one', () => {
    renderNav({ absences: { count: 3, label: '3', ariaLabel: '3 richieste da approvare' } })

    expect(screen.getByLabelText('3 richieste da approvare')).toHaveTextContent('3')
    expect(screen.getAllByLabelText(/da approvare/)).toHaveLength(1)
  })

  it('shows nothing when the entry has no badge', () => {
    renderNav({ absences: null })

    expect(screen.queryByLabelText(/da approvare/)).not.toBeInTheDocument()
  })
})
