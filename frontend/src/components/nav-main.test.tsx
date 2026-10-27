import { beforeAll, describe, expect, it } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
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

function renderNav(badges?: Parameters<typeof NavMain>[0]['badges'], open = true) {
  return render(
    <MemoryRouter>
      <SidebarProvider defaultOpen={open}>
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

  it('keeps the count readable with the sidebar collapsed, in the entry tooltip', async () => {
    renderNav({ absences: { count: 3, label: '3', ariaLabel: '3 richieste da approvare' } }, false)

    fireEvent.focus(screen.getByRole('link', { name: /Assenze/ }))

    expect(await screen.findByRole('tooltip')).toHaveTextContent('Assenze (3)')
  })

  it('shows nothing when the entry has no badge', () => {
    renderNav({ absences: null })

    expect(screen.queryByLabelText(/da approvare/)).not.toBeInTheDocument()
  })
})

describe('NavMain single-child groups (user rule 2026-10-27)', () => {
  function renderItems(items: NavigationItem[]) {
    return render(
      <MemoryRouter>
        <SidebarProvider>
          <NavMain items={items} />
        </SidebarProvider>
      </MemoryRouter>,
    )
  }

  const leaf = (key: string, label: string): NavigationItem => ({
    key, label, icon: null, route: `/${key}`, type: 'item', children: [],
  })

  it('renders a top-level group with one child as a section heading over a plain link, not a collapsible', () => {
    renderItems([
      { key: 'tasks-group', label: 'navigation.tasks', icon: null, route: null, type: 'item', children: [leaf('tasks', 'navigation.absences')] },
    ])

    expect(screen.queryByRole('button', { name: 'Task' })).not.toBeInTheDocument()
    expect(screen.getByText('Task')).toHaveAttribute('data-sidebar', 'group-label')
    expect(screen.getByRole('link', { name: 'Assenze' })).toHaveAttribute('href', '/tasks')
  })

  it('lifts the lone child of a group nested in a section straight into the section', () => {
    renderItems([
      {
        key: 'accounting', label: 'navigation.accounting', icon: null, route: null, type: 'section',
        children: [
          { key: 'accounting-payable', label: 'navigation.accountingPayable', icon: null, route: null, type: 'item', children: [leaf('purchase-invoices', 'navigation.purchaseInvoices')] },
        ],
      },
    ])

    expect(screen.queryByText('Passiva')).not.toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Fatture passive' })).toHaveAttribute('href', '/purchase-invoices')
  })

  it('keeps a group with two or more children collapsible', () => {
    renderItems([
      { key: 'tasks-group', label: 'navigation.tasks', icon: null, route: null, type: 'item', children: [leaf('tasks', 'navigation.absences'), leaf('task-types', 'navigation.taskTypes')] },
    ])

    expect(screen.getByRole('button', { name: 'Task' })).toBeInTheDocument()
  })
})
