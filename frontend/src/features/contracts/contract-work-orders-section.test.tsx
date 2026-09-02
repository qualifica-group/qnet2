import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createRef, forwardRef, useImperativeHandle } from 'react'
import { render, screen } from '@testing-library/react'
import {
  ContractWorkOrdersSection,
  type ContractWorkOrdersSectionHandle,
} from '@/features/contracts/contract-work-orders-section'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type { TableActionDefinition, TableRow, TableRowScope } from '@/features/table/types'

/**
 * Spec 0095 D-8/D-9/D-10: the Contract detail's Commesse tab — a thin wiring
 * layer over the SAME `<TableView domain="work-orders">` and
 * `useWorkOrderRowActions` the standalone Commesse page uses, scoped via
 * `rowScope={{quoteId}}` and forced into a modal so a row action never
 * abandons the Contract. `TableView` and `useModuleOpener` are stubbed
 * (owned/tested at their own layer, mirrors `opportunity-quotes-section.test.tsx`).
 */
const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => canMock(permission), hasRole: () => false, roles: [], isLoading: false }),
}))

const openViewMock = vi.fn()
const openEditMock = vi.fn()
const useModuleOpenerMock = vi.fn()
vi.mock('@/features/modules/use-module-opener', () => ({
  useModuleOpener: (...args: unknown[]) => {
    useModuleOpenerMock(...args)
    return {
      openCreate: vi.fn(),
      openCreateWith: vi.fn(),
      openView: openViewMock,
      openEdit: openEditMock,
      openDuplicate: vi.fn(),
      sheet: null,
    }
  },
}))

const deleteWorkOrderMock = vi.fn()
vi.mock('@/features/work-orders/api', () => ({
  WORK_ORDERS_DOMAIN: 'work-orders',
  deleteWorkOrder: (...args: unknown[]) => deleteWorkOrderMock(...args),
}))

const ROW: TableRow = { id: 9, actions: ['view', 'edit', 'delete', 'activity'], title: 'Installazione impianto', code: 'COM-0009' }

const refreshMock = vi.fn()
interface TableViewStubProps {
  domain: string
  rowScope?: TableRowScope
  onAction?: (action: TableActionDefinition, row: TableRow) => void
}

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<{ refresh: () => void }, TableViewStubProps>(function TableViewStub(
    { domain, rowScope, onAction },
    ref,
  ) {
    useImperativeHandle(ref, () => ({ refresh: refreshMock }))
    return (
      <div role="region" aria-label={`table-${domain}-${rowScope?.quoteId ?? 'none'}`}>
        <button type="button" onClick={() => onAction?.({ key: 'view', label: 'actions.view', icon: 'eye', type: 'action', confirm: false }, ROW)}>
          view row
        </button>
        <button type="button" onClick={() => onAction?.({ key: 'delete', label: 'actions.delete', icon: 'trash', type: 'danger', confirm: true }, ROW)}>
          delete row
        </button>
      </div>
    )
  }),
}))

beforeEach(() => {
  canMock.mockReset()
  canMock.mockReturnValue(true)
  useModuleOpenerMock.mockReset()
  openViewMock.mockReset()
  openEditMock.mockReset()
  deleteWorkOrderMock.mockReset()
  refreshMock.mockReset()
})

describe('ContractWorkOrdersSection — permission gate (mirrors OpportunityQuotesSection)', () => {
  it('renders nothing without work-orders.viewAny', () => {
    canMock.mockReturnValue(false)
    render(<ContractWorkOrdersSection quoteId={3} />)
    expect(screen.queryByRole('region')).not.toBeInTheDocument()
  })

  it('mounts <TableView domain="work-orders"> scoped by rowScope.quoteId (D-8)', () => {
    render(<ContractWorkOrdersSection quoteId={3} />)
    expect(screen.getByRole('region', { name: 'table-work-orders-3' })).toBeInTheDocument()
  })
})

describe('ContractWorkOrdersSection — row actions (D-9)', () => {
  it('forces the modal open mode so a row action never abandons the Contract', () => {
    render(<ContractWorkOrdersSection quoteId={3} />)
    expect(useModuleOpenerMock).toHaveBeenCalledWith(
      'work-orders',
      expect.objectContaining({ forceMode: OPEN_MODE_MODAL }),
    )
  })

  it('opens the Commessa on the "view" row action', () => {
    render(<ContractWorkOrdersSection quoteId={3} />)
    screen.getByRole('button', { name: 'view row' }).click()
    expect(openViewMock).toHaveBeenCalledWith(ROW)
  })

  it('refreshes the grid after a successful delete', async () => {
    deleteWorkOrderMock.mockResolvedValue(undefined)
    render(<ContractWorkOrdersSection quoteId={3} />)
    screen.getByRole('button', { name: 'delete row' }).click()
    await vi.waitFor(() => expect(refreshMock).toHaveBeenCalled())
  })
})

describe('ContractWorkOrdersSection — exposed handle (AC-062)', () => {
  it('forwards refresh() to the underlying grid, a no-op when the section is not mounted', () => {
    const ref = createRef<ContractWorkOrdersSectionHandle>()
    render(<ContractWorkOrdersSection ref={ref} quoteId={3} />)

    ref.current?.refresh()

    expect(refreshMock).toHaveBeenCalledTimes(1)
  })
})
