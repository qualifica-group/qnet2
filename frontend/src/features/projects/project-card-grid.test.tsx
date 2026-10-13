import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ProjectCardGrid } from '@/features/projects/project-card-grid'
import type { ProjectCard } from '@/features/projects/types'
import type { AdvancedFilterDescriptor } from '@/features/table/advanced-filters/types'
import type { TableConfig } from '@/features/table/types'

const useProjectCardsMock = vi.fn()
const useTableConfigMock = vi.fn()
const saveFiltersMutateMock = vi.fn()
const fetchNextPage = vi.fn()
const refetch = vi.fn()

vi.mock('@/features/projects/use-project-cards', () => ({
  useProjectCards: (...args: unknown[]) => useProjectCardsMock(...args),
}))

// Advanced filters (spec 0032 AC-018): the card grid reuses `useTableConfig`
// (the same catalog/applied-state source `TableView` reads for the AG Grid
// view) and `useSaveTableFilters` (persistence) — both mocked here so the
// panel/toggle behavior is tested in isolation from the network.
vi.mock('@/features/table/use-table-config', () => ({
  useTableConfig: (...args: unknown[]) => useTableConfigMock(...args),
}))

vi.mock('@/features/table/use-table-filters', () => ({
  useSaveTableFilters: () => ({ mutate: saveFiltersMutateMock, isPending: false }),
}))

// The detail itself is covered by its own tests; here we only assert that the
// pencil opens it — editable in place (spec 0220 D-4) — in the Sheet.
vi.mock('@/features/projects/project-screens', () => ({
  ProjectDetailScreen: ({ id }: { id: number }) => <div>project-detail:{id}</div>,
}))

function buildCard(overrides: Partial<ProjectCard> = {}): ProjectCard {
  return {
    id: 1,
    code: 'PRJ-0001',
    name: 'Alpha project',
    description: null,
    pipeline_status: { id: 1, name: 'Active', color: 'green' },
    campaigns_count: 3,
    leads_count: 20,
    geo_scope: 'country',
    geo_label: 'Italy',
    total_budget: '1000.00',
    allocated_budget: '400.00',
    remaining_budget: '600.00',
    start_date: null,
    end_date: null,
    can: { update: true, delete: true },
    ...overrides,
  }
}

interface BaseState {
  data: { pages: { items: ProjectCard[]; pagination: unknown }[] } | undefined
  isPending: boolean
  isError: boolean
  fetchNextPage: typeof fetchNextPage
  hasNextPage: boolean
  isFetchingNextPage: boolean
  refetch: typeof refetch
}

function baseState(overrides: Partial<BaseState> = {}): BaseState {
  return {
    data: undefined,
    isPending: false,
    isError: false,
    fetchNextPage,
    hasNextPage: false,
    isFetchingNextPage: false,
    refetch,
    ...overrides,
  }
}

function pagesOf(items: ProjectCard[]) {
  return { pages: [{ items, pagination: { total: items.length, offset: 0, limit: 12, total_pages: 1 } }] }
}

function renderGrid() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })

  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter>
        <ProjectCardGrid />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

/** Minimal, schema-valid descriptor fixture. */
function descriptor(
  overrides: Partial<AdvancedFilterDescriptor> & Pick<AdvancedFilterDescriptor, 'name' | 'type'>,
): AdvancedFilterDescriptor {
  return {
    label: 'Status',
    order: 0,
    required: false,
    visible: true,
    width: 'md',
    multiple: false,
    ...overrides,
  }
}

beforeEach(() => {
  useProjectCardsMock.mockReset()
  useTableConfigMock.mockReset()
  saveFiltersMutateMock.mockReset()
  fetchNextPage.mockReset()
  refetch.mockReset()
  // Default: no advanced filter catalog (existing behavior, toggle hidden).
  useTableConfigMock.mockReturnValue({ data: undefined })
})

describe('ProjectCardGrid (spec 0026 AC-007/009)', () => {
  it('renders cards from a mocked page', () => {
    useProjectCardsMock.mockReturnValue(baseState({ data: pagesOf([buildCard()]) }))

    renderGrid()

    expect(screen.getByText('PRJ-0001')).toBeInTheDocument()
    expect(screen.getByText('Alpha project')).toBeInTheDocument()
    expect(screen.getByText('Active')).toBeInTheDocument()
  })

  it('shows skeleton cards while the first page is loading', () => {
    useProjectCardsMock.mockReturnValue(baseState({ isPending: true }))

    const { container } = renderGrid()

    expect(container.querySelectorAll('[data-slot="skeleton"]').length).toBeGreaterThan(0)
  })

  it('shows the empty state when there are no projects', () => {
    useProjectCardsMock.mockReturnValue(baseState({ data: pagesOf([]) }))

    renderGrid()

    expect(screen.getByText('No projects found.')).toBeInTheDocument()
  })

  it('shows the error state with a retry that refetches', () => {
    useProjectCardsMock.mockReturnValue(baseState({ isError: true }))

    renderGrid()

    fireEvent.click(screen.getByRole('button', { name: 'Retry' }))
    expect(refetch).toHaveBeenCalled()
  })

  it('loads the next page when the bottom sentinel intersects (infinite scroll)', async () => {
    useProjectCardsMock.mockReturnValue(
      baseState({ data: pagesOf([buildCard()]), hasNextPage: true }),
    )

    type ObserverCallback = (entries: { isIntersecting: boolean }[]) => void
    let trigger: ObserverCallback | null = null
    const observe = vi.fn()
    vi.stubGlobal(
      'IntersectionObserver',
      class {
        constructor(cb: ObserverCallback) {
          trigger = cb
        }
        observe = observe
        unobserve() {}
        disconnect() {}
        takeRecords() {
          return []
        }
      },
    )

    renderGrid()

    await waitFor(() => expect(observe).toHaveBeenCalled())
    const fire = trigger as ObserverCallback | null
    fire?.([{ isIntersecting: true }])
    expect(fetchNextPage).toHaveBeenCalled()

    vi.unstubAllGlobals()
  })

  it('hides the edit affordance when can.update is false', () => {
    useProjectCardsMock.mockReturnValue(
      baseState({ data: pagesOf([buildCard({ can: { update: false, delete: false } })]) }),
    )

    renderGrid()

    expect(screen.queryByRole('button', { name: 'Edit project' })).not.toBeInTheDocument()
  })

  it('shows the edit affordance when can.update is true', () => {
    useProjectCardsMock.mockReturnValue(baseState({ data: pagesOf([buildCard()]) }))

    renderGrid()

    expect(screen.getByRole('button', { name: 'Edit project' })).toBeInTheDocument()
  })

  // REQUIREMENT CHANGED (spec 0220 D-4): the pencil opens the in-place-editable detail, not an edit form.
  it('opens the project detail in the sheet on the pencil instead of navigating to the dedicated page', async () => {
    useProjectCardsMock.mockReturnValue(baseState({ data: pagesOf([buildCard({ id: 7 })]) }))

    renderGrid()

    fireEvent.click(screen.getByRole('button', { name: 'Edit project' }))

    expect(await screen.findByRole('dialog')).toBeInTheDocument()
    expect(screen.getByText('project-detail:7')).toBeInTheDocument()
  })

  it('shows the derived geo scope badge (spec 0027 AC-012)', () => {
    useProjectCardsMock.mockReturnValue(
      baseState({ data: pagesOf([buildCard({ geo_scope: 'city', geo_label: 'Milan' })]) }),
    )

    renderGrid()

    expect(screen.getByText('City')).toBeInTheDocument()
    expect(screen.getByText('Milan')).toBeInTheDocument()
  })

  it('omits the geo scope badge when the project has no geo at all', () => {
    useProjectCardsMock.mockReturnValue(
      baseState({ data: pagesOf([buildCard({ geo_scope: null, geo_label: null })]) }),
    )

    renderGrid()

    expect(screen.queryByText('National')).not.toBeInTheDocument()
  })
})

describe('ProjectCardGrid — advanced filters (spec 0032 AC-018)', () => {
  it('hides the advanced-filters toggle when the domain declares no catalog', () => {
    useProjectCardsMock.mockReturnValue(baseState({ data: pagesOf([]) }))

    renderGrid()

    expect(
      screen.queryByRole('button', { name: 'Advanced filters' }),
    ).not.toBeInTheDocument()
  })

  it('shows the toggle and, once opened, the same field-per-type panel the AG Grid view uses', () => {
    useTableConfigMock.mockReturnValue({
      data: {
        advancedFilters: [descriptor({ name: 'status', type: 'text', label: 'Status' })],
        appliedAdvancedFilters: null,
      } as unknown as TableConfig,
    })
    useProjectCardsMock.mockReturnValue(baseState({ data: pagesOf([]) }))

    renderGrid()

    const toggle = screen.getByRole('button', { name: 'Advanced filters' })
    fireEvent.click(toggle)

    expect(screen.getByRole('textbox', { name: 'Status' })).toBeInTheDocument()
  })

  it('applying an advanced filter sends the SAME advancedFilters shape to useProjectCards as the AG Grid view sends to POST /rows', async () => {
    useTableConfigMock.mockReturnValue({
      data: {
        advancedFilters: [descriptor({ name: 'status', type: 'text', label: 'Status' })],
        appliedAdvancedFilters: null,
      } as unknown as TableConfig,
    })
    useProjectCardsMock.mockReturnValue(baseState({ data: pagesOf([]) }))

    renderGrid()

    fireEvent.click(screen.getByRole('button', { name: 'Advanced filters' }))
    fireEvent.change(screen.getByRole('textbox', { name: 'Status' }), {
      target: { value: 'won' },
    })
    fireEvent.click(screen.getByRole('button', { name: 'Apply' }))

    await waitFor(() =>
      expect(useProjectCardsMock).toHaveBeenLastCalledWith({
        advancedFilters: { status: 'won' },
      }),
    )
    // Persisted the same way the AG Grid path persists (spec 0032), so
    // switching views resumes the same applied state.
    expect(saveFiltersMutateMock).toHaveBeenCalledWith({
      advancedFilters: { status: 'won' },
    })
  })

  it('shows the active-filter count as a badge on the toggle', async () => {
    useTableConfigMock.mockReturnValue({
      data: {
        advancedFilters: [descriptor({ name: 'status', type: 'text', label: 'Status' })],
        appliedAdvancedFilters: null,
      } as unknown as TableConfig,
    })
    useProjectCardsMock.mockReturnValue(baseState({ data: pagesOf([]) }))

    renderGrid()

    fireEvent.click(screen.getByRole('button', { name: 'Advanced filters' }))
    fireEvent.change(screen.getByRole('textbox', { name: 'Status' }), {
      target: { value: 'won' },
    })
    fireEvent.click(screen.getByRole('button', { name: 'Apply' }))

    // The toggle's own accessible name stays stable ("Advanced filters"); the
    // count is a separately-labeled badge, mirroring NotificationBell's unread badge.
    await waitFor(() =>
      expect(screen.getByLabelText('1 active filter')).toBeInTheDocument(),
    )
    expect(screen.getByRole('button', { name: 'Advanced filters' })).toBeInTheDocument()
  })
})
