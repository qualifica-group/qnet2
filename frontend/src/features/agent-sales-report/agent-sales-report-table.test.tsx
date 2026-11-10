import { beforeAll, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { AgentSalesReportTable } from '@/features/agent-sales-report/agent-sales-report-table'
import type { TableViewProps } from '@/features/table/table-view-props'

/**
 * Spec 0365 AC-015: the adapter mounts the generic grid on the
 * `agent-sales-report` domain and replaces the single generic Export item
 * with the three-entry menu. The grid itself is stubbed: it renders the
 * export menu the way the toolbar would.
 */

const viewProps = vi.hoisted(() => ({ current: null as TableViewProps | null }))

vi.mock('@/components/page-header', () => ({ PageHeader: () => null }))
vi.mock('@/features/agent-sales-report/export-menu', () => ({
  AgentSalesReportExportMenu: () => <p>export-menu-stub</p>,
}))
vi.mock('@/features/table/table-view', () => ({
  TableView: (props: TableViewProps): ReactNode => {
    viewProps.current = props
    return <div role="grid" aria-label={props.domain}>{props.exportMenu?.({
      gridApi: null,
      columns: [],
      search: '',
      advancedFilters: {},
      customFilterRules: null,
      openGenericExport: vi.fn(),
    })}</div>
  },
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('AgentSalesReportTable (spec 0365)', () => {
  it('mounts the grid on the agent-sales-report domain with the custom export menu', () => {
    render(<AgentSalesReportTable />)

    expect(screen.getByRole('grid', { name: 'agent-sales-report' })).toBeInTheDocument()
    expect(screen.getByText('export-menu-stub')).toBeInTheDocument()
    expect(viewProps.current?.disableBuiltinDelete).toBe(true)
  })
})
