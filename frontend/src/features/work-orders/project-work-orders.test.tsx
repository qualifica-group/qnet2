import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle } from 'react'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import ProjectWorkOrdersPage from '@/pages/project-work-orders-page'
import { WorkOrdersTable } from '@/features/work-orders/work-orders-table'

/**
 * Spec 0359 D-1/D-2: "Commesse" sends `workOrderType: 'processing'` (back again, user decision 2026-11-25), "Commesse di Progetto"
 * mounts the `project-work-orders` domain with no row scope. Neither page has the category strip. Grid and row actions are stubbed.
 */
vi.mock('@/components/page-header', () => ({ PageHeader: () => <div /> }))
/** The permissions the stubbed `<Can>` grants. */
const granted = vi.hoisted(() => new Set<string>())
vi.mock('@/features/auth/can', () => ({
  Can: ({ permission, children, fallback }: { permission: string; children: React.ReactNode; fallback?: React.ReactNode }) => (
    <>{granted.has(permission) ? children : fallback}</>
  ),
}))
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
  TableView: forwardRef<{ refresh: () => void }, { domain: string; rowScope?: { workOrderType?: string }; scope?: { attributeColumnsFromFilters?: boolean } }>(
    function TableViewStub({ domain, rowScope, scope }, ref) {
      useImperativeHandle(ref, () => ({ refresh: vi.fn() }))
      return (
        <div role="region" aria-label={`table-${domain}-${rowScope?.workOrderType ?? 'unscoped'}`}>
          <p>{scope?.attributeColumnsFromFilters ? 'attr-columns-from-filters' : 'no-attr-columns'}</p>
        </div>
      )
    },
  ),
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  granted.clear()
  granted.add('project-work-orders.viewAny')
})

describe('work orders pages (spec 0359)', () => {
  // REQUIREMENT CHANGED (2026-11-25): "Commesse" lists processing work orders only, project ones are in their own module.
  it('"Commesse" narrows the work-orders grid to processing work orders, without the category strip', () => {
    render(
      <MemoryRouter>
        <WorkOrdersTable />
      </MemoryRouter>,
    )

    expect(screen.getByRole('region', { name: 'table-work-orders-processing' })).toBeInTheDocument()
    expect(screen.getByText('attr-columns-from-filters')).toBeInTheDocument()
    expect(screen.queryByRole('tablist')).not.toBeInTheDocument()
  })

  it('"Commesse di Progetto" mounts the project-work-orders grid, unscoped and without the category strip', () => {
    render(
      <MemoryRouter>
        <ProjectWorkOrdersPage />
      </MemoryRouter>,
    )

    expect(screen.getByRole('region', { name: 'table-project-work-orders-unscoped' })).toBeInTheDocument()
    expect(screen.getByText('no-attr-columns')).toBeInTheDocument()
    expect(screen.queryByRole('tablist')).not.toBeInTheDocument()
  })

  it('"Commesse di Progetto" needs its own project-work-orders.viewAny, not the "Commesse" one (2026-11-25)', () => {
    granted.clear()
    granted.add('work-orders.viewAny')
    render(
      <MemoryRouter>
        <ProjectWorkOrdersPage />
      </MemoryRouter>,
    )

    expect(screen.queryByRole('region')).not.toBeInTheDocument()
    expect(screen.getByText(i18n.t('workOrders.forbidden'))).toBeInTheDocument()
  })
})
