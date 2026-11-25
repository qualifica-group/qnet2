import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { ProjectWorkOrdersTable } from '@/features/work-orders/project-work-orders-table'
import { WorkOrdersTable } from '@/features/work-orders/work-orders-table'

/**
 * Spec 0431: "Commesse di Progetto" creates a project work order born without
 * an offer, gated by `work-orders.create`; "Commesse" never offers a create.
 * Grid, row actions and work-phase tabs are stubbed.
 */
vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))
const granted = vi.hoisted(() => new Set<string>())
vi.mock('@/features/auth/can', () => ({
  Can: ({ permission, children }: { permission: string; children: ReactNode }) => (
    <>{granted.has(permission) ? children : null}</>
  ),
}))
vi.mock('@/features/work-orders/work-phase-tabs', () => ({ WorkPhaseTabs: () => <div>work-phase-tabs</div> }))
vi.mock('@/features/work-orders/use-work-order-row-actions', () => ({
  useWorkOrderRowActions: () => ({
    handleAction: vi.fn(),
    resolveActionHref: vi.fn(),
    iconMap: {},
    renderers: {},
    sheet: null,
    dialogs: null,
  }),
}))
vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<{ refresh: () => void }>(function TableViewStub(_props, ref) {
    useImperativeHandle(ref, () => ({ refresh: vi.fn() }))
    return <div />
  }),
}))

const newButton = () => screen.queryByRole('link', { name: i18n.t('workOrders.form.newStandalone') })

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  granted.clear()
})

describe('"New project work order" (spec 0431)', () => {
  it('links to the create page for who may create work orders', () => {
    granted.add('work-orders.create')
    render(
      <MemoryRouter>
        <ProjectWorkOrdersTable />
      </MemoryRouter>,
    )

    expect(newButton()).toHaveAttribute('href', '/project-work-orders/new')
  })

  it('is hidden without work-orders.create', () => {
    render(
      <MemoryRouter>
        <ProjectWorkOrdersTable />
      </MemoryRouter>,
    )

    expect(newButton()).not.toBeInTheDocument()
  })

  it('never appears on "Commesse"', () => {
    granted.add('work-orders.create')
    render(
      <MemoryRouter>
        <WorkOrdersTable />
      </MemoryRouter>,
    )

    expect(newButton()).not.toBeInTheDocument()
    expect(screen.getByText('work-phase-tabs')).toBeInTheDocument()
  })
})
