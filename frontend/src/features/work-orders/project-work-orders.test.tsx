import { beforeAll, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle } from 'react'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import ProjectWorkOrdersPage from '@/pages/project-work-orders-page'
import { WorkOrdersTable } from '@/features/work-orders/work-orders-table'

/**
 * Spec 0359 D-1/D-2: "Commesse" sends `workOrderType: 'processing'`, "Commesse di Progetto" mounts the
 * `project-work-orders` domain with no row scope. Neither page has the category strip. Grid and row actions are stubbed.
 */
vi.mock('@/components/page-header', () => ({ PageHeader: () => <div /> }))
vi.mock('@/features/work-orders/work-order-proforma-dialog', () => ({ WorkOrderProformaDialog: () => null }))
vi.mock('@/features/activity-log/resource-activity-dialog', () => ({ ResourceActivityDialog: () => null }))
vi.mock('@/features/auth/can', () => ({ Can: ({ children }: { children: React.ReactNode }) => <>{children}</> }))
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

describe('work orders pages (spec 0359)', () => {
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
})
