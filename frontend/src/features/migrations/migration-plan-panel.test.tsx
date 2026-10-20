import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { MigrationPlanPanel } from '@/features/migrations/migration-plan-panel'
import type { MigrationPlan } from '@/features/migrations/types'

/**
 * Spec 0046 AC-013: the mass-import plan panel renders every source in the
 * saved order with an "include" checkbox, and Save persists the reordered +
 * re-toggled plan. Drag reorder is exercised through the shared SortableList's
 * keyboard path (jsdom has no pointer layout); the API module is mocked.
 */

const fetchMigrationPlanMock = vi.fn()
const saveMigrationPlanMock = vi.fn()

vi.mock('@/features/migrations/api', () => ({
  fetchMigrationPlan: (...args: unknown[]) => fetchMigrationPlanMock(...args),
  saveMigrationPlan: (...args: unknown[]) => saveMigrationPlanMock(...args),
}))

const PLAN: MigrationPlan = {
  sources: [
    { source: 'companies', label: 'Companies', enabled: true, available: true },
    { source: 'users', label: 'Users', enabled: false, available: true },
    { source: 'roles', label: 'Roles', enabled: true, available: true },
  ],
}

const ROW_HEIGHT = 40

/** jsdom returns all-zero rects; stub `top` in DOM order so the keyboard sensor can pick a direction. */
function mockRowRects() {
  vi.spyOn(HTMLElement.prototype, 'getBoundingClientRect').mockImplementation(function (
    this: HTMLElement,
  ) {
    const rows = Array.from(document.querySelectorAll('li'))
    const index = rows.indexOf(this as HTMLLIElement)
    const top = index === -1 ? 0 : index * ROW_HEIGHT
    return {
      width: 280,
      height: ROW_HEIGHT,
      top,
      bottom: top + ROW_HEIGHT,
      left: 0,
      right: 280,
      x: 0,
      y: top,
      toJSON: () => ({}),
    } as DOMRect
  })
}

/** The keyboard sensor attaches its move/drop listeners in a macrotask. */
async function flushSensorAttach() {
  await new Promise((resolve) => setTimeout(resolve, 0))
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchMigrationPlanMock.mockReset().mockResolvedValue(PLAN)
  saveMigrationPlanMock.mockReset().mockResolvedValue(PLAN)
  mockRowRects()
})

afterEach(() => {
  vi.restoreAllMocks()
})

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

describe('MigrationPlanPanel', () => {
  it('renders sources in plan order with include checkboxes reflecting enabled', async () => {
    render(<MigrationPlanPanel open onOpenChange={vi.fn()} />, { wrapper: wrapper() })

    expect(await screen.findByText('Companies')).toBeInTheDocument()

    const checkboxes = screen.getAllByRole('checkbox')
    expect(checkboxes[0]).toBeChecked() // companies
    expect(checkboxes[1]).not.toBeChecked() // users
    expect(checkboxes[2]).toBeChecked() // roles
  })

  it('saves the reordered and re-toggled plan', async () => {
    render(<MigrationPlanPanel open onOpenChange={vi.fn()} />, { wrapper: wrapper() })

    await screen.findByText('Companies')

    // Enable the disabled 'users'.
    fireEvent.click(screen.getAllByRole('checkbox')[1])

    // Move 'roles' (3rd handle) up one, so order becomes companies, roles, users.
    const handles = screen.getAllByRole('button', { name: 'Reorder source' })
    handles[2].focus()
    fireEvent.keyDown(handles[2], { code: 'Space' })
    await flushSensorAttach()
    fireEvent.keyDown(document, { code: 'ArrowUp' })
    fireEvent.keyDown(document, { code: 'Space' })

    fireEvent.click(screen.getByRole('button', { name: 'Save order' }))

    await waitFor(() => expect(saveMigrationPlanMock).toHaveBeenCalledTimes(1))
    expect(saveMigrationPlanMock.mock.calls[0][0]).toEqual([
      { source: 'companies', enabled: true },
      { source: 'roles', enabled: true },
      { source: 'users', enabled: true },
    ])
  })

  it('disables the include checkbox of a source this installation may not import', async () => {
    fetchMigrationPlanMock.mockResolvedValue({
      sources: [
        { source: 'users', label: 'Users', enabled: true, available: true },
        { source: 'business-function-members', label: 'Members', enabled: false, available: false },
      ],
    })
    render(<MigrationPlanPanel open onOpenChange={vi.fn()} />, { wrapper: wrapper() })

    await screen.findByText('Not available')

    const checkboxes = screen.getAllByRole('checkbox')
    expect(checkboxes[0]).toBeEnabled()
    expect(checkboxes[1]).toBeDisabled()
    expect(checkboxes[1]).not.toBeChecked()
  })
})
