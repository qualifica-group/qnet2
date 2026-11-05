import { beforeAll, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle } from 'react'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import ProjectWorkOrdersPage from '@/pages/project-work-orders-page'
import { WorkOrdersTable } from '@/features/work-orders/work-orders-table'

/**
 * Spec 0359 D-1/D-2: "Commesse" sends `workOrderType: 'processing'`, "Commesse di Progetto" mounts the
 * `project-work-orders` domain with no row scope and no category strip. Grid, row actions and strip are stubbed.
 */
vi.mock('@/components/page-header', () => ({ PageHeader: () => <div /> }))
vi.mock('@/features/work-orders/work-order-proforma-dialog', () => ({ WorkOrderProformaDialog: () => null }))
vi.mock('@/features/activity-log/resource-activity-dialog', () => ({ ResourceActivityDialog: () => null }))
vi.mock('@/features/auth/can', () => ({ Can: ({ children }: { children: React.ReactNode }) => <>{children}</> }))
vi.mock('@/features/category-tabs/category-tabs', () => ({ CategoryTabs: () => <div role="tablist" aria-label="categories" /> }))
vi.mock('@/features/category-tabs/use-category-tab-strip', () => ({
  useCategoryTabStrip: () => ({ scope: undefined, tableKey: 'all', tabsProps: {}, invalidateCategories: vi.fn() }),
}))
vi.mock('@/features/work-orders/use-work-order-row-actions', () => ({
  useWorkOrderRowActions: () => ({
    handleAction: vi.fn(),
    isBusy: () => false,
    activityRow: null,
    closeActivity: vi.fn(),
    proformaRow: null,
    closeProforma: vi.fn(),
    resolveActionState: undefined,
    iconMap: {},
    renderers: {},
    sheet: null,
    forceCloseDialog: null,
    progressDialog: null,
  }),
}))

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<{ refresh: () => void }, { domain: string; rowScope?: { workOrderType?: string } }>(
    function TableViewStub({ domain, rowScope }, ref) {
      useImperativeHandle(ref, () => ({ refresh: vi.fn() }))
      return <div role="region" aria-label={`table-${domain}-${rowScope?.workOrderType ?? 'unscoped'}`} />
    },
  ),
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('work orders pages (spec 0359)', () => {
  it('"Commesse" narrows the work-orders grid to processing work orders', () => {
    render(
      <MemoryRouter>
        <WorkOrdersTable />
      </MemoryRouter>,
    )

    expect(screen.getByRole('region', { name: 'table-work-orders-processing' })).toBeInTheDocument()
    expect(screen.getByRole('tablist', { name: 'categories' })).toBeInTheDocument()
  })

  it('"Commesse di Progetto" mounts the project-work-orders grid, unscoped and without the category strip', () => {
    render(
      <MemoryRouter>
        <ProjectWorkOrdersPage />
      </MemoryRouter>,
    )

    expect(screen.getByRole('region', { name: 'table-project-work-orders-unscoped' })).toBeInTheDocument()
    expect(screen.queryByRole('tablist', { name: 'categories' })).not.toBeInTheDocument()
  })
})
