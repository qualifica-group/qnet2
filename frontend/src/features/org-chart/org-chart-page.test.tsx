import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import OrgChartPage from '@/pages/org-chart-page'
import { FUNCTIONS_FIXTURE, PEOPLE_FIXTURE } from '@/features/org-chart/fixtures.test-support'

const fetchFunctionsMock = vi.fn()
const fetchPeopleMock = vi.fn()
const canMock = vi.fn()

vi.mock('@/features/org-chart/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/org-chart/api')>()),
  fetchOrgChartFunctions: () => fetchFunctionsMock(),
  fetchOrgChartPeople: () => fetchPeopleMock(),
}))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => canMock(permission), isLoading: false }),
}))
vi.mock('@/routes/breadcrumbs', () => ({ AppBreadcrumbs: () => null }))
vi.mock('@/components/detail/record-link', () => ({
  RecordLink: ({ domain, id, children }: { domain: string; id: number; children: React.ReactNode }) => (
    <a href={`/${domain}/${id}`}>{children}</a>
  ),
}))

function renderPage() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <OrgChartPage />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  fetchFunctionsMock.mockReset().mockResolvedValue(FUNCTIONS_FIXTURE)
  fetchPeopleMock.mockReset().mockResolvedValue(PEOPLE_FIXTURE)
  canMock.mockReset().mockReturnValue(true)
})

describe('OrgChartPage', () => {
  it('shows the forbidden message without org-chart.view and does not fetch', () => {
    canMock.mockReturnValue(false)
    renderPage()
    expect(screen.getByText(/Non hai i permessi/)).toBeInTheDocument()
    expect(fetchFunctionsMock).not.toHaveBeenCalled()
  })

  it('renders the functions tree: type badge, manager, count, link to the function and the all-functions group', async () => {
    renderPage()
    const tree = await screen.findByRole('tree', { name: 'Funzioni' })
    const sales = within(tree).getByRole('treeitem', { name: /Sales/ })
    expect(within(sales).getAllByText('Business unit').length).toBeGreaterThan(0)
    expect(within(sales).getAllByText(/Responsabile: Bruno Neri/).length).toBeGreaterThan(0)
    expect(screen.getByRole('link', { name: 'Sales' })).toHaveAttribute('href', '/business-functions/2')
    expect(screen.getAllByText('Business service')).toHaveLength(1)
    expect(within(tree).getByRole('treeitem', { name: /Tutte le funzioni/ })).toBeInTheDocument()
    expect(screen.getAllByRole('img', { name: 'Responsabile' }).length).toBeGreaterThan(0)
    expect(screen.getByText('Account')).toBeInTheDocument()
  })

  it('switches to the people tree and nests the direct reports', async () => {
    renderPage()
    fireEvent.mouseDown(await screen.findByRole('tab', { name: 'Persone' }))
    const tree = await screen.findByRole('tree', { name: 'Persone' })
    const roots = within(tree).getAllByRole('treeitem').filter((item) => item.getAttribute('aria-level') === '1')
    expect(roots).toHaveLength(2)
    expect(within(tree).getAllByRole('treeitem', { name: /Carla Gallo/ })).toHaveLength(2)
  })

  it('search keeps the branches with results, expands them and reports no match', async () => {
    renderPage()
    await screen.findByRole('tree')
    fireEvent.click(screen.getByRole('button', { name: 'Comprimi tutto' }))
    expect(screen.queryByRole('treeitem', { name: /Carla Gallo/ })).not.toBeInTheDocument()

    fireEvent.change(screen.getByRole('searchbox', { name: "Cerca nell'organigramma" }), { target: { value: 'carla' } })
    expect(await screen.findByRole('treeitem', { name: /Carla Gallo/ })).toBeInTheDocument()
    expect(screen.queryByRole('treeitem', { name: /Support/ })).not.toBeInTheDocument()

    fireEvent.change(screen.getByRole('searchbox'), { target: { value: 'zzz' } })
    expect(await screen.findByText('Nessun risultato per questa ricerca.')).toBeInTheDocument()
  })

  it('collapse all hides the children and expand all restores them', async () => {
    renderPage()
    await screen.findByRole('tree')
    expect(screen.getByRole('treeitem', { name: /Carla Gallo/ })).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Comprimi tutto' }))
    expect(screen.queryByRole('treeitem', { name: /Carla Gallo/ })).not.toBeInTheDocument()
    expect(screen.getAllByRole('treeitem')).toHaveLength(2)
    fireEvent.click(screen.getByRole('button', { name: 'Espandi tutto' }))
    expect(screen.getByRole('treeitem', { name: /Carla Gallo/ })).toBeInTheDocument()
  })

  it('supports the tree keyboard model: arrows, collapse/expand, Enter opens the link', async () => {
    renderPage()
    const tree = await screen.findByRole('tree')
    const company = within(tree).getByRole('treeitem', { name: /Company/ })
    company.focus()
    expect(company).toHaveAttribute('aria-expanded', 'true')

    fireEvent.keyDown(company, { key: 'ArrowDown' })
    const sales = within(tree).getByRole('treeitem', { name: /Sales/ })
    expect(sales).toHaveFocus()

    fireEvent.keyDown(sales, { key: 'ArrowLeft' })
    expect(sales).toHaveAttribute('aria-expanded', 'false')
    fireEvent.keyDown(sales, { key: 'ArrowLeft' })
    expect(company).toHaveFocus()
    fireEvent.keyDown(company, { key: 'ArrowLeft' })
    expect(company).toHaveAttribute('aria-expanded', 'false')
    fireEvent.keyDown(company, { key: 'ArrowRight' })
    expect(company).toHaveAttribute('aria-expanded', 'true')
    fireEvent.keyDown(company, { key: 'ArrowRight' })
    expect(within(tree).getByRole('treeitem', { name: /Sales/ })).toHaveFocus()
    fireEvent.keyDown(tree.querySelector('[data-node-key="all"]') as HTMLElement, { key: 'ArrowUp' })

    const link = within(company).getByRole('link', { name: 'Company' })
    const onClick = vi.fn((event: Event) => event.preventDefault())
    link.addEventListener('click', onClick)
    fireEvent.keyDown(company, { key: 'Enter' })
    expect(onClick).toHaveBeenCalledTimes(1)
  })

  it('exposes stable node attributes for the move feature', async () => {
    renderPage()
    const tree = await screen.findByRole('tree')
    const item = tree.querySelector('[data-node-key="f:1/f:2/p:11"]')
    expect(item).toHaveAttribute('data-node-kind', 'person')
    expect(item).toHaveAttribute('data-node-id', '11')
    expect(item).toHaveAttribute('aria-level', '3')
  })

  it('shows the loading skeleton, the empty state, the error and the 403 message', async () => {
    fetchFunctionsMock.mockReturnValue(new Promise(() => {}))
    const { unmount } = renderPage()
    expect(await screen.findByLabelText(/Caricamento/)).toHaveAttribute('aria-busy', 'true')
    unmount()

    fetchFunctionsMock.mockResolvedValue({ functions: [], all_functions_people: [] })
    const empty = renderPage()
    expect(await screen.findByText('Nessuna funzione aziendale.')).toBeInTheDocument()
    empty.unmount()

    fetchFunctionsMock.mockRejectedValue({ response: { status: 500 } })
    const failed = renderPage()
    expect(await screen.findByRole('alert')).toHaveTextContent(/Impossibile caricare/)
    failed.unmount()

    fetchFunctionsMock.mockRejectedValue({ response: { status: 403 } })
    renderPage()
    await waitFor(() => expect(screen.getByRole('alert')).toHaveTextContent(/Non hai i permessi/))
  })
})
