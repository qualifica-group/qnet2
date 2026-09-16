import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle } from 'react'
import { fireEvent, render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import { WorkOrderTasksSection } from '@/features/work-orders/work-order-tasks-section'
import type { TableRowScope } from '@/features/table/types'

/**
 * Spec 0133 D-3/D-4 (AC-009, AC-011): the Commessa detail's Task panel is a thin
 * wiring layer over the SAME `<TableView domain="tasks">` and
 * `useModuleOpener` the Task page uses, scoped via `rowScope={{workOrderId}}`.
 * Both are stubbed (owned/tested at their own layer, mirrors
 * `contract-work-orders-section.test.tsx`).
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
    return {
      openCreate: vi.fn(),
      openCreateWith: openCreateWithMock,
      openView: vi.fn(),
      openEdit: vi.fn(),
      openDuplicate: vi.fn(),
      sheet: null,
    }
  },
}))

interface TableViewStubProps {
  domain: string
  rowScope?: TableRowScope
  onRowCountChanged?: (count: number | null) => void
}

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<{ refresh: () => void }, TableViewStubProps>(function TableViewStub(
    { domain, rowScope, onRowCountChanged },
    ref,
  ) {
    useImperativeHandle(ref, () => ({ refresh: vi.fn() }))
    return (
      <div role="region" aria-label={`table-${domain}-${rowScope?.workOrderId ?? 'none'}`}>
        <button type="button" onClick={() => onRowCountChanged?.(7)}>
          report count
        </button>
      </div>
    )
  }),
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
  canMock.mockReturnValue(true)
  openCreateWithMock.mockReset()
  useModuleOpenerMock.mockReset()
})

describe('WorkOrderTasksSection — grid (AC-009)', () => {
  it('mounts <TableView domain="tasks"> scoped by rowScope.workOrderId', () => {
    render(<WorkOrderTasksSection workOrderId={4} />)
    expect(screen.getByRole('region', { name: 'table-tasks-4' })).toBeInTheDocument()
  })

  it('forces the modal open mode so a task never abandons the Commessa', () => {
    render(<WorkOrderTasksSection workOrderId={4} />)
    expect(useModuleOpenerMock).toHaveBeenCalledWith('tasks', expect.objectContaining({ forceMode: OPEN_MODE_MODAL }))
  })

  it('shows the live row count reported by the grid', () => {
    render(<WorkOrderTasksSection workOrderId={4} />)
    fireEvent.click(screen.getByRole('button', { name: 'report count' }))
    expect(screen.getByLabelText('7 tasks')).toHaveTextContent('7')
  })
})

describe('WorkOrderTasksSection — "New task" (AC-011)', () => {
  it('opens the create form seeded with this work order', () => {
    render(<WorkOrderTasksSection workOrderId={4} />)
    fireEvent.click(screen.getByRole('button', { name: 'New task' }))
    expect(openCreateWithMock).toHaveBeenCalledWith({ work_order_id: 4 })
  })

  it('hides the button without tasks.create', () => {
    canMock.mockImplementation((permission) => permission !== 'tasks.create')
    render(<WorkOrderTasksSection workOrderId={4} />)
    expect(screen.queryByRole('button', { name: 'New task' })).not.toBeInTheDocument()
  })
})
