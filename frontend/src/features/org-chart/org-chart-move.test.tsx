import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { act, fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import { MemoryRouter } from 'react-router-dom'
import type { DragEndEvent, DragStartEvent } from '@dnd-kit/core'
import i18n from '@/i18n'
import OrgChartPage from '@/pages/org-chart-page'
import { buildPeopleTree } from '@/features/org-chart/build-people-tree'
import { FUNCTIONS_FIXTURE, PEOPLE_FIXTURE } from '@/features/org-chart/fixtures.test-support'

const fetchFunctionsMock = vi.fn()
const fetchPeopleMock = vi.fn()
const moveManagerMock = vi.fn()
const moveParentMock = vi.fn()
const moveFunctionMock = vi.fn()
const canMock = vi.fn()
const toastSuccess = vi.fn()
const toastError = vi.fn()
const dnd: { onDragStart?: (e: DragStartEvent) => void; onDragEnd?: (e: DragEndEvent) => void } = {}

vi.mock('@/features/org-chart/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/org-chart/api')>()),
  fetchOrgChartFunctions: () => fetchFunctionsMock(),
  fetchOrgChartPeople: () => fetchPeopleMock(),
  moveOrgPersonManager: (...args: unknown[]) => moveManagerMock(...args),
  moveOrgFunctionParent: (...args: unknown[]) => moveParentMock(...args),
  moveOrgPersonFunction: (...args: unknown[]) => moveFunctionMock(...args),
}))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => canMock(permission), isLoading: false }),
}))
vi.mock('sonner', () => ({ toast: { success: (m: string) => toastSuccess(m), error: (m: string) => toastError(m) } }))
vi.mock('@/routes/breadcrumbs', () => ({ AppBreadcrumbs: () => null }))
vi.mock('@/components/detail/record-link', () => ({
  RecordLink: ({ domain, id, children }: { domain: string; id: number; children: React.ReactNode }) => (
    <a href={`/${domain}/${id}`}>{children}</a>
  ),
}))
vi.mock('@dnd-kit/core', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@dnd-kit/core')>()
  return {
    ...actual,
    DndContext: (props: { onDragStart: (e: DragStartEvent) => void; onDragEnd: (e: DragEndEvent) => void; children: React.ReactNode }) => {
      dnd.onDragStart = props.onDragStart
      dnd.onDragEnd = props.onDragEnd
      return <>{props.children}</>
    },
  }
})

let client: QueryClient
function renderPage() {
  client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <OrgChartPage />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

function serverError(status: number, message: string) {
  return new AxiosError('x', undefined, undefined, undefined, {
    status,
    data: { success: false, message },
  } as never)
}


async function chooseDestination(optionName: string) {
  fireEvent.click(screen.getByRole('combobox', { name: 'Destinazione' }))
  fireEvent.click(await screen.findByRole('option', { name: optionName }))
}

async function openMenuAndMove(treeName: string, itemName: RegExp) {
  const tree = await screen.findByRole('tree', { name: treeName })
  const item = within(tree).getAllByRole('treeitem', { name: itemName })[0]
  const trigger = within(item).getAllByRole('button', { name: /Azioni per/ })[0]
  fireEvent.pointerDown(trigger, { button: 0, ctrlKey: false })
  fireEvent.click(await screen.findByRole('menuitem', { name: 'Sposta in…' }))
  return screen.findByRole('dialog')
}

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  fetchFunctionsMock.mockReset().mockResolvedValue(FUNCTIONS_FIXTURE)
  fetchPeopleMock.mockReset().mockResolvedValue(PEOPLE_FIXTURE)
  moveManagerMock.mockReset().mockResolvedValue(undefined)
  moveParentMock.mockReset().mockResolvedValue(undefined)
  moveFunctionMock.mockReset().mockResolvedValue(undefined)
  canMock.mockReset().mockReturnValue(true)
  toastSuccess.mockReset()
  toastError.mockReset()
})

describe('org chart move - "Sposta in…"', () => {
  it('hides the action without the permission (functions need business-functions.update, people users.update)', async () => {
    canMock.mockImplementation((p: string) => p === 'org-chart.view' || p === 'users.update')
    renderPage()
    const tree = await screen.findByRole('tree', { name: 'Funzioni' })
    const sales = within(tree).getByRole('treeitem', { name: /Sales/ })
    expect(within(sales).queryByRole('button', { name: 'Azioni per Sales' })).not.toBeInTheDocument()
    expect(within(tree).getAllByRole('button', { name: /Azioni per Carla Gallo/ }).length).toBeGreaterThan(0)
    expect(screen.queryByRole('button', { name: /Azioni per Dario Fiore/ })).not.toBeInTheDocument()
  })

  it('moves a function: search, confirm, payload, invalidation and toast', async () => {
    renderPage()
    await screen.findByRole('tree')
    const invalidate = vi.spyOn(client, 'invalidateQueries')
    const dialog = await openMenuAndMove('Funzioni', /Sales/)
    await chooseDestination('Nessuna funzione padre (radice)')
    expect(within(dialog).getByText('Sposta Sales da «Company» a «Nessuna funzione padre (radice)».')).toBeInTheDocument()
    fireEvent.click(within(dialog).getByRole('button', { name: 'Conferma lo spostamento' }))
    await waitFor(() => expect(moveParentMock).toHaveBeenCalledWith(2, { parent_id: null }))
    await waitFor(() => expect(toastSuccess).toHaveBeenCalled())
    expect(invalidate).toHaveBeenCalledWith({ queryKey: ['org-chart'] })
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
  })

  it('moves a person between functions with the competence warning', async () => {
    renderPage()
    const dialog = await openMenuAndMove('Funzioni', /Carla Gallo/)
    fireEvent.click(within(dialog).getByRole('combobox', { name: 'Destinazione' }))
    fireEvent.change(await screen.findByPlaceholderText('Cerca la destinazione…'), { target: { value: 'supp' } })
    expect(screen.queryByRole('option', { name: 'Company' })).not.toBeInTheDocument()
    fireEvent.click(screen.getByRole('option', { name: 'Support' }))
    expect(within(dialog).getByRole('note')).toHaveTextContent(/assegnazioni automatiche/)
    fireEvent.click(within(dialog).getByRole('button', { name: 'Conferma lo spostamento' }))
    await waitFor(() => expect(moveFunctionMock).toHaveBeenCalledWith(12, { from_function_id: 2, to_function_id: 3 }))
  })

  it('moves a person under another manager in the people view', async () => {
    renderPage()
    fireEvent.mouseDown(await screen.findByRole('tab', { name: 'Persone' }))
    const dialog = await openMenuAndMove('Persone', /Bruno Neri/)
    await chooseDestination('Dario Fiore')
    expect(within(dialog).queryByRole('note')).not.toBeInTheDocument()
    fireEvent.click(within(dialog).getByRole('button', { name: 'Conferma lo spostamento' }))
    await waitFor(() => expect(moveManagerMock).toHaveBeenCalledWith(2, { from_manager_id: 1, to_manager_id: 4 }))
  })

  it('shows the server message on 422 and the i18n copy on 403, keeping the dialog open', async () => {
    renderPage()
    moveParentMock.mockRejectedValueOnce(serverError(422, 'Spostamento non valido: ciclo.'))
    const dialog = await openMenuAndMove('Funzioni', /Sales/)
    await chooseDestination('Support')
    fireEvent.click(within(dialog).getByRole('button', { name: 'Conferma lo spostamento' }))
    await waitFor(() => expect(toastError).toHaveBeenCalledWith('Spostamento non valido: ciclo.'))

    moveParentMock.mockRejectedValueOnce(serverError(403, 'Forbidden'))
    fireEvent.click(within(dialog).getByRole('button', { name: 'Conferma lo spostamento' }))
    await waitFor(() => expect(toastError).toHaveBeenLastCalledWith('Non hai i permessi per eseguire questo spostamento.'))
    expect(screen.getByRole('dialog')).toBeInTheDocument()
  })
})

describe('org chart move - drag and drop', () => {
  const peopleNodes = buildPeopleTree(PEOPLE_FIXTURE)
  const dario = peopleNodes.find((n) => n.key === 'p:4')!
  const bruno = peopleNodes[0].children.find((n) => n.key === 'p:1/p:2')!

  async function mountPeople() {
    renderPage()
    fireEvent.mouseDown(await screen.findByRole('tab', { name: 'Persone' }))
    await screen.findByRole('tree', { name: 'Persone' })
  }

  const dragEvent = (node: unknown) => ({ active: { data: { current: { node } } } }) as unknown as DragStartEvent
  const endEvent = (node: unknown) => ({ over: node ? { data: { current: { node } } } : null }) as unknown as DragEndEvent

  it('a valid drop opens the confirmation and moves through the same mutation', async () => {
    await mountPeople()
    act(() => dnd.onDragStart?.(dragEvent(dario)))
    act(() => dnd.onDragEnd?.(endEvent(bruno)))
    const dialog = await screen.findByRole('dialog')
    expect(within(dialog).getByText(/Sposta Dario Fiore da «Nessun responsabile \(radice\)» a «Bruno Neri»/)).toBeInTheDocument()
    fireEvent.click(within(dialog).getByRole('button', { name: 'Conferma lo spostamento' }))
    await waitFor(() => expect(moveManagerMock).toHaveBeenCalledWith(4, { from_manager_id: null, to_manager_id: 2 }))
  })

  it('an invalid drop (itself, a descendant, nothing) opens nothing and calls nothing', async () => {
    await mountPeople()
    const anna = peopleNodes[0]
    act(() => dnd.onDragStart?.(dragEvent(anna)))
    act(() => dnd.onDragEnd?.(endEvent(bruno)))
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()

    act(() => dnd.onDragStart?.(dragEvent(bruno)))
    act(() => dnd.onDragEnd?.(endEvent(bruno.children[0])))
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()

    act(() => dnd.onDragStart?.(dragEvent(dario)))
    act(() => dnd.onDragEnd?.(endEvent(null)))
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(moveManagerMock).not.toHaveBeenCalled()
  })
})
