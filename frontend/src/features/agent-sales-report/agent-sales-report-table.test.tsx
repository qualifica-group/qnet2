import { beforeAll, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { AgentSalesReportTable } from '@/features/agent-sales-report/agent-sales-report-table'
import type { TableViewProps } from '@/features/table/table-view-props'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * Spec 0365 AC-015: the adapter mounts the generic grid on the
 * `agent-sales-report` domain and replaces the single generic Export item
 * with the three-entry menu. The grid itself is stubbed: it renders the
 * export menu the way the toolbar would.
 */

const viewProps = vi.hoisted(() => ({ current: null as TableViewProps | null }))
const opener = vi.hoisted(() => ({ openView: vi.fn(), options: null as unknown }))

vi.mock('@/features/modules/use-module-opener', () => ({
  useModuleOpener: (domain: string, options: unknown) => {
    opener.options = { domain, ...(options as object) }
    return { openView: opener.openView, sheet: null }
  },
}))

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
    render(<AgentSalesReportTable />, { wrapper: MemoryRouter })

    expect(screen.getByRole('grid', { name: 'agent-sales-report' })).toBeInTheDocument()
    expect(screen.getByText('export-menu-stub')).toBeInTheDocument()
    expect(viewProps.current?.disableBuiltinDelete).toBe(true)
  })

  it('opens the offer in a modal from the view row action, the link kept for a new tab (task 427625)', () => {
    render(<AgentSalesReportTable />, { wrapper: MemoryRouter })
    const view = { key: 'view' } as TableActionDefinition
    const row = { id: 42 } as TableRow

    expect(viewProps.current?.resolveActionHref?.(view, row)).toBe('/quotes/42')
    expect(viewProps.current?.resolveActionHref?.({ key: 'other' } as TableActionDefinition, row)).toBeUndefined()

    viewProps.current?.onAction(view, row)
    expect(opener.openView).toHaveBeenCalledWith(row)
    expect(opener.options).toMatchObject({ domain: 'quotes', forceMode: 'modal' })
  })
})
