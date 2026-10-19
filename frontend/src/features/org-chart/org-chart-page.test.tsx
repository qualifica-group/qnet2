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

  it('renders the function cards: type, manager, link, unassigned card; people are cards under their function', async () => {
    renderPage()
    const tree = await screen.findByRole('tree', { name: 'Funzioni' })
    const sales = within(tree).getByRole('treeitem', { name: 'Sales' })
    expect(within(sales).getByText('Business unit')).toBeInTheDocument()
    expect(within(sales).getByText('Responsabile: Bruno Neri')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Sales' })).toHaveAttribute('href', '/business-functions/2')
    expect(within(tree).getByRole('treeitem', { name: 'Support' })).toHaveTextContent('Nessun responsabile')
    expect(within(tree).getByRole('treeitem', { name: 'Non assegnati' })).toBeInTheDocument()
    expect(within(tree).getByRole('treeitem', { name: /Anna Rossi/ })).toHaveAttribute('aria-level', '2')
    expect(within(tree).queryByRole('treeitem', { name: /Carla Gallo/ })).not.toBeInTheDocument()
  })

  it('opens the members panel of a function with managers first, then the other people', async () => {
    renderPage()
    await screen.findByRole('tree')
    fireEvent.click(screen.getByRole('button', { name: "Sales: 2 persone, apri l'elenco" }))
    const panel = await screen.findByRole('dialog', { name: 'Sales' })
    const [managers, people] = within(panel).getAllByRole('list')
    expect(within(managers).getByText('Bruno Neri')).toBeInTheDocument()
    expect(within(managers).getByText('Account')).toBeInTheDocument()
    expect(within(people).getByText('Carla Gallo')).toBeInTheDocument()
    expect(within(panel).getByText('Responsabili')).toBeInTheDocument()
  })

  it('switches to the people chart: one card per person, a person with two managers twice once expanded', async () => {
    renderPage()
    fireEvent.mouseDown(await screen.findByRole('tab', { name: 'Persone' }))
    const tree = await screen.findByRole('tree', { name: 'Persone' })
    const roots = within(tree).getAllByRole('treeitem').filter((item) => item.getAttribute('aria-level') === '1')
    expect(roots).toHaveLength(2)
    expect(within(tree).getAllByRole('treeitem', { name: /Carla Gallo/ })).toHaveLength(1)
    // Anna: corner crown + crown on the function she heads (AC-023).
    expect(within(tree).getAllByRole('img', { name: 'Responsabile' })).toHaveLength(2)

    fireEvent.click(within(tree).getByRole('button', { name: 'Espandi' }))
    expect(within(tree).getAllByRole('treeitem', { name: /Carla Gallo/ })).toHaveLength(2)
  })

  it('puts a crown only before the function the person heads (AC-023)', async () => {
    renderPage()
    fireEvent.mouseDown(await screen.findByRole('tab', { name: 'Persone' }))
    const tree = await screen.findByRole('tree', { name: 'Persone' })
    const anna = within(tree).getByRole('treeitem', { name: /Anna Rossi/ })
    const company = within(anna).getByText('Company')
    const sales = within(anna).getByText('Sales')
    expect(within(company.parentElement as HTMLElement).getByRole('img', { name: 'Responsabile' })).toBeInTheDocument()
    expect(within(sales.parentElement as HTMLElement).queryByRole('img')).not.toBeInTheDocument()
  })

  it('starts with the roots open; the pill shows the hidden cards and toggles the branch', async () => {
    renderPage()
    fireEvent.mouseDown(await screen.findByRole('tab', { name: 'Persone' }))
    const tree = await screen.findByRole('tree', { name: 'Persone' })
    const bruno = within(tree).getByRole('treeitem', { name: /Bruno Neri/ })
    expect(bruno).toHaveAttribute('aria-expanded', 'false')
    expect(within(bruno).getByRole('button', { name: 'Espandi' })).toHaveTextContent('+1')

    const anna = within(tree).getByRole('treeitem', { name: /Anna Rossi/ })
    fireEvent.click(within(anna).getByRole('button', { name: 'Comprimi' }))
    expect(within(tree).queryByRole('treeitem', { name: /Bruno Neri/ })).not.toBeInTheDocument()
    expect(within(anna).getByRole('button', { name: 'Espandi' })).toHaveTextContent('+3')
  })

  it('search opens the branches with results, highlights the matches and reports no match', async () => {
    renderPage()
    fireEvent.mouseDown(await screen.findByRole('tab', { name: 'Persone' }))
    await screen.findByRole('tree', { name: 'Persone' })
    fireEvent.click(screen.getByRole('button', { name: 'Comprimi tutto' }))
    expect(screen.queryByRole('treeitem', { name: /Carla Gallo/ })).not.toBeInTheDocument()

    fireEvent.change(screen.getByRole('searchbox', { name: "Cerca nell'organigramma" }), { target: { value: 'carla' } })
    const matches = await screen.findAllByRole('treeitem', { name: /Carla Gallo/ })
    expect(matches).toHaveLength(2)
    expect(matches[0].querySelector('[data-chart-card]')).toHaveClass('border-primary')
    expect(screen.getByRole('treeitem', { name: /Anna Rossi/ }).querySelector('[data-chart-card]')).not.toHaveClass('border-primary')
    expect(screen.queryByRole('treeitem', { name: /Dario Fiore/ })).not.toBeInTheDocument()

    fireEvent.change(screen.getByRole('searchbox'), { target: { value: 'zzz' } })
    expect(await screen.findByText('Nessun risultato per questa ricerca.')).toBeInTheDocument()
  })

  it('collapse all hides the children and expand all restores them', async () => {
    renderPage()
    await screen.findByRole('tree')
    expect(screen.getByRole('treeitem', { name: 'Sales' })).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Comprimi tutto' }))
    expect(screen.queryByRole('treeitem', { name: 'Sales' })).not.toBeInTheDocument()
    expect(screen.getAllByRole('treeitem')).toHaveLength(2)
    fireEvent.click(screen.getByRole('button', { name: 'Espandi tutto' }))
    expect(screen.getByRole('treeitem', { name: 'Sales' })).toBeInTheDocument()
  })

  it('zooms in and out within the limits and shows the percentage', async () => {
    renderPage()
    await screen.findByRole('tree')
    expect(screen.getByText('100%')).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Ingrandisci' }))
    expect(screen.getByText('110%')).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Riduci' }))
    fireEvent.click(screen.getByRole('button', { name: 'Riduci' }))
    expect(screen.getByText('90%')).toBeInTheDocument()
    expect(screen.getByRole('tree').getAttribute('style')).toContain('scale(0.9)')
  })

  it('supports the tree keyboard model: arrows, collapse/expand, Enter opens the link', async () => {
    renderPage()
    const tree = await screen.findByRole('tree')
    const company = within(tree).getByRole('treeitem', { name: 'Company' })
    company.focus()
    expect(company).toHaveAttribute('aria-expanded', 'true')

    fireEvent.keyDown(company, { key: 'ArrowDown' })
    const anna = within(tree).getByRole('treeitem', { name: /Anna Rossi/ })
    expect(anna).toHaveFocus()

    fireEvent.keyDown(anna, { key: 'ArrowDown' })
    const sales = within(tree).getByRole('treeitem', { name: 'Sales' })
    expect(sales).toHaveFocus()

    fireEvent.keyDown(sales, { key: 'ArrowLeft' })
    expect(company).toHaveFocus()
    fireEvent.keyDown(company, { key: 'ArrowLeft' })
    expect(company).toHaveAttribute('aria-expanded', 'false')
    fireEvent.keyDown(company, { key: 'ArrowRight' })
    expect(company).toHaveAttribute('aria-expanded', 'true')
    fireEvent.keyDown(company, { key: 'ArrowRight' })
    expect(within(tree).getByRole('treeitem', { name: /Anna Rossi/ })).toHaveFocus()

    const link = within(company).getByRole('link', { name: 'Company' })
    const onClick = vi.fn((event: Event) => event.preventDefault())
    link.addEventListener('click', onClick)
    fireEvent.keyDown(company, { key: 'Enter' })
    expect(onClick).toHaveBeenCalledTimes(1)
  })

  it('exposes stable node attributes and the flat ARIA position of every card', async () => {
    renderPage()
    const tree = await screen.findByRole('tree')
    const item = tree.querySelector('[data-node-key="f:1/f:3"]')
    expect(item).toHaveAttribute('data-node-kind', 'function')
    expect(item).toHaveAttribute('data-node-id', '3')
    expect(item).toHaveAttribute('aria-level', '2')
    expect(item).toHaveAttribute('aria-posinset', '3')
    expect(item).toHaveAttribute('aria-setsize', '3')
  })

  it('shows the loading skeleton, the empty state, the error and the 403 message', async () => {
    fetchFunctionsMock.mockReturnValue(new Promise(() => {}))
    const { unmount } = renderPage()
    expect(await screen.findByLabelText(/Caricamento/)).toHaveAttribute('aria-busy', 'true')
    unmount()

    fetchFunctionsMock.mockResolvedValue({ functions: [], unassigned_people: [] })
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
