import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import CommissionAccrualsPage from '@/pages/commission-accruals-page'
import { CommissionAccrualsTable } from '@/features/commission-accruals/commission-accruals-table'
import type { TableRowsAggregates } from '@/features/table/types'

const canMock = vi.fn<(permission: string) => boolean>()

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => canMock(permission), hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('@/components/page-header', () => ({ PageHeader: () => <div /> }))

interface StubProps {
  domain: string
  forcedFilterModel?: Record<string, unknown>
  onAction?: unknown
  getBulkActions?: unknown
  isRowSelectable?: unknown
  renderFooter?: (aggregates: TableRowsAggregates | undefined) => ReactNode
}

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<{ refresh: () => void }, StubProps>(function TableViewStub(
    { domain, forcedFilterModel, getBulkActions, isRowSelectable, renderFooter },
    ref,
  ) {
    useImperativeHandle(ref, () => ({ refresh: () => {}, setFilterModel: () => {} }))
    return (
      <div
        role="region"
        aria-label={`table-${domain}`}
        data-forced={JSON.stringify(forcedFilterModel)}
        data-write-props={String(getBulkActions !== undefined || isRowSelectable !== undefined)}
      >
        {renderFooter?.({ amount: 1250.5 })}
      </div>
    )
  }),
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset().mockReturnValue(true)
})

describe('CommissionAccrualsTable (spec 0212 AC-021)', () => {
  it('mounts the ledger domain forced to the open status, with the Open tab selected', () => {
    render(<CommissionAccrualsTable />)

    const region = screen.getByRole('region', { name: 'table-commission-accruals' })
    expect(JSON.parse(region.getAttribute('data-forced') ?? '{}')).toEqual({
      status: { filterType: 'set', values: ['open'] },
    })
    expect(screen.getByRole('tab', { name: 'Open', selected: true })).toBeInTheDocument()
    expect(screen.getByRole('tab', { name: 'Settled externally', selected: false })).toBeInTheDocument()
    expect(screen.getByRole('tab', { name: 'All', selected: false })).toBeInTheDocument()
  })

  it('renders the footer total from the server aggregates', () => {
    render(<CommissionAccrualsTable />)

    expect(screen.getByRole('group', { name: 'Total of the current filter' })).toHaveTextContent('Amount')
  })

  it('offers no edit, create or bulk action', () => {
    render(<CommissionAccrualsTable />)

    expect(screen.getByRole('region', { name: 'table-commission-accruals' })).toHaveAttribute('data-write-props', 'false')
    expect(screen.queryByRole('button')).not.toBeInTheDocument()
  })
})

describe('CommissionAccrualsPage', () => {
  it('gates the table on commission-accruals.viewAny', () => {
    canMock.mockReturnValue(false)

    render(
      <MemoryRouter>
        <CommissionAccrualsPage />
      </MemoryRouter>,
    )

    expect(canMock).toHaveBeenCalledWith('commission-accruals.viewAny')
    expect(screen.getByText("You don't have permission to view commission accruals.")).toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'table-commission-accruals' })).not.toBeInTheDocument()
  })
})
