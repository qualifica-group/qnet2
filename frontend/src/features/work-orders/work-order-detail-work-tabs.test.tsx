import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, within } from '@testing-library/react'
import { createPortal } from 'react-dom'
import i18n from '@/i18n'
import { WorkOrderDetailWorkTabs } from '@/features/work-orders/work-order-detail-work-tabs'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'

const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => canMock(permission) }),
}))

// The board's own header actions reach the strip through the slot it is handed.
vi.mock('@/features/work-orders/task-board/work-order-task-board', () => ({
  WorkOrderTaskBoard: ({ actionsContainer }: { actionsContainer: HTMLElement | null }) => (
    <>
      <div>task-board</div>
      {actionsContainer ? createPortal(<button type="button">board-action</button>, actionsContainer) : null}
    </>
  ),
}))

vi.mock('@/features/work-order-costs/work-order-costs-section', () => ({
  WorkOrderCostsSection: ({ canManage }: { canManage: boolean }) => <div>costs-section manage:{String(canManage)}</div>,
}))

function workOrder(actions: Record<string, boolean>): WorkOrderDetailWithPermissions {
  return {
    id: 4,
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions,
    },
  } as WorkOrderDetailWithPermissions
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
  canMock.mockReturnValue(true)
})

describe('WorkOrderDetailWorkTabs', () => {
  it('opens the Task tab by default, with the costs content not mounted', () => {
    render(<WorkOrderDetailWorkTabs workOrder={workOrder({ view_costs: true })} />)

    const tasksTab = screen.getByRole('tab', { name: 'Tasks' })
    expect(tasksTab).toHaveAttribute('data-state', 'active')
    expect(tasksTab.querySelector('svg')).not.toBeNull()
    expect(screen.getByText('task-board')).toBeInTheDocument()
    expect(screen.queryByText(/costs-section/)).not.toBeInTheDocument()
  })

  it('switches to Costi and back, the board actions following the Task tab', () => {
    render(<WorkOrderDetailWorkTabs workOrder={workOrder({ view_costs: true, manage_costs: true })} />)

    const strip = screen.getByRole('tablist').parentElement as HTMLElement
    expect(within(strip).getByRole('button', { name: 'board-action' })).toBeInTheDocument()

    fireEvent.mouseDown(screen.getByRole('tab', { name: 'Costs' }))

    expect(screen.getByText('costs-section manage:true')).toBeInTheDocument()
    expect(screen.queryByText('task-board')).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'board-action' })).not.toBeInTheDocument()

    fireEvent.mouseDown(screen.getByRole('tab', { name: 'Tasks' }))

    expect(screen.getByText('task-board')).toBeInTheDocument()
  })

  it('shows only the Costi tab without tasks.viewAny', () => {
    canMock.mockReturnValue(false)
    render(<WorkOrderDetailWorkTabs workOrder={workOrder({ view_costs: true })} />)

    expect(screen.queryByRole('tab', { name: 'Tasks' })).not.toBeInTheDocument()
    expect(screen.getByRole('tab', { name: 'Costs' })).toHaveAttribute('data-state', 'active')
    expect(screen.getByText('costs-section manage:false')).toBeInTheDocument()
  })

  it('shows only the Task tab without view_costs', () => {
    render(<WorkOrderDetailWorkTabs workOrder={workOrder({})} />)

    expect(screen.getByRole('tab', { name: 'Tasks' })).toBeInTheDocument()
    expect(screen.queryByRole('tab', { name: 'Costs' })).not.toBeInTheDocument()
  })

  it('renders nothing when neither tab is authorized', () => {
    canMock.mockReturnValue(false)
    const { container } = render(<WorkOrderDetailWorkTabs workOrder={workOrder({})} />)

    expect(container).toBeEmptyDOMElement()
  })

  it('has no Contract data tab even with view_contract_data: it moved to the record card', () => {
    render(<WorkOrderDetailWorkTabs workOrder={workOrder({ view_costs: true, view_contract_data: true })} />)

    expect(screen.getAllByRole('tab').map((tab) => tab.textContent)).toEqual(['Tasks', 'Costs'])
    expect(screen.getByRole('tab', { name: 'Tasks' })).toHaveAttribute('data-state', 'active')
  })
})
