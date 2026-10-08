import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, type ComponentType } from 'react'
import { fireEvent, render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import {
  RegistryCommissionConfigurationsPanel,
  RegistryOpportunitiesPanel,
  RegistryQuotesPanel,
  RegistryTasksPanel,
  RegistryWorkOrdersPanel,
  type RegistryRelatedPanelProps,
} from '@/features/registries/registry-related-panels'
import type { TableRowScope } from '@/features/table/types'

/**
 * Spec 0199: each panel of the anagrafica's related records is the module's
 * OWN grid scoped to the client (`rowScope.registryId`), its row actions
 * forced into the Sheet, and a "Nuovo" gated by the module's `create` that
 * opens the module's create with `registry_id`. `TableView` and
 * `useModuleOpener` are stubbed: they are tested at their own layer.
 */
const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => canMock(permission), hasRole: () => false, roles: [], isLoading: false }),
}))

const openCreateWithMock = vi.fn()
const useModuleOpenerMock = vi.fn()
vi.mock('@/features/modules/use-module-opener', () => ({
  useModuleOpener: (...args: unknown[]) => {
    useModuleOpenerMock(...args)
    return { openCreate: vi.fn(), openCreateWith: openCreateWithMock, openView: vi.fn(), openDuplicate: vi.fn(), sheet: null }
  },
}))

interface TableViewStubProps {
  domain: string
  rowScope?: TableRowScope
  onRowCountChanged?: (count: number | null) => void
  advancedFiltersOverride?: Record<string, unknown>
}

let capturedOverride: Record<string, unknown> | undefined

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<unknown, TableViewStubProps>(function TableViewStub({
    domain,
    rowScope,
    onRowCountChanged,
    advancedFiltersOverride,
  }) {
    capturedOverride = advancedFiltersOverride
    return (
      <div role="region" aria-label={`table-${domain}-${rowScope?.registryId ?? 'none'}`}>
        <button type="button" onClick={() => onRowCountChanged?.(3)}>
          report count
        </button>
      </div>
    )
  }),
}))

interface PanelCase {
  name: string
  Panel: ComponentType<RegistryRelatedPanelProps>
  domain: string
  createPermission: string
  createLabel: string
}

const CASES: PanelCase[] = [
  {
    name: 'Opportunita',
    Panel: RegistryOpportunitiesPanel,
    domain: 'opportunities',
    createPermission: 'opportunities.create',
    createLabel: 'New opportunity',
  },
  { name: 'Offerte', Panel: RegistryQuotesPanel, domain: 'quotes', createPermission: 'quotes.create', createLabel: 'New quote' },
  {
    name: 'Commesse',
    Panel: RegistryWorkOrdersPanel,
    domain: 'work-orders',
    createPermission: 'work-orders.create',
    createLabel: 'New work order',
  },
  { name: 'Task', Panel: RegistryTasksPanel, domain: 'tasks', createPermission: 'tasks.create', createLabel: 'New task' },
  // Spec 0204: the supplier's configured commissions, same mechanics.
  {
    name: 'Commissioni configurate',
    Panel: RegistryCommissionConfigurationsPanel,
    domain: 'commission-configurations',
    createPermission: 'commission-configurations.create',
    createLabel: 'New configuration',
  },
]

const REGISTRY_ID = 7

function renderPanel(Panel: ComponentType<RegistryRelatedPanelProps>, onRowCountChanged = vi.fn()) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <Panel registryId={REGISTRY_ID} onRowCountChanged={onRowCountChanged} />
    </QueryClientProvider>,
  )
  return onRowCountChanged
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.clearAllMocks()
  canMock.mockReturnValue(true)
})

describe.each(CASES)('$name panel (spec 0199)', ({ Panel, domain, createPermission, createLabel }) => {
  it("renders the module's grid scoped to the anagrafica", () => {
    renderPanel(Panel)
    expect(screen.getByRole('region', { name: `table-${domain}-${REGISTRY_ID}` })).toBeInTheDocument()
  })

  it('forces create/view into the Sheet so the anagrafica is never left', () => {
    renderPanel(Panel)
    expect(useModuleOpenerMock).toHaveBeenCalledWith(domain, expect.objectContaining({ forceMode: OPEN_MODE_MODAL }))
  })

  it('opens the module create bound to the anagrafica', () => {
    renderPanel(Panel)
    fireEvent.click(screen.getByRole('button', { name: createLabel }))
    expect(openCreateWithMock).toHaveBeenCalledWith({ registry_id: REGISTRY_ID })
  })

  it("offers no create without the module's create permission", () => {
    canMock.mockImplementation((permission) => permission !== createPermission)
    renderPanel(Panel)
    expect(screen.queryByRole('button', { name: createLabel })).not.toBeInTheDocument()
  })

  it("forwards the grid's total to the tab counter", () => {
    const onRowCountChanged = renderPanel(Panel)
    fireEvent.click(screen.getByRole('button', { name: 'report count' }))
    expect(onRowCountChanged).toHaveBeenCalledWith(3)
  })
})

describe('Task panel assignment (spec 0199)', () => {
  it("starts on every visible task, not on the Task page's 'assigned to me'", () => {
    renderPanel(RegistryTasksPanel)
    expect(capturedOverride).toEqual({ assignment: ['visible'] })
  })

  it('falls back to every role for an actor without viewAll/viewSite', () => {
    canMock.mockImplementation((permission) => permission !== 'tasks.viewAll' && permission !== 'tasks.viewSite')
    renderPanel(RegistryTasksPanel)
    expect(capturedOverride).toEqual({ assignment: ['all'] })
  })

  it('leaves the other modules on their own defaults', () => {
    renderPanel(RegistryQuotesPanel)
    expect(capturedOverride).toBeUndefined()
  })
})
