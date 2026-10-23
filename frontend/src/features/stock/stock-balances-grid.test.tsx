import { createRef } from 'react'
import { describe, expect, it, vi } from 'vitest'
import { render } from '@testing-library/react'
import { StockBalancesGrid } from '@/features/stock/stock-balances-grid'
import type { TableViewHandle } from '@/features/table/table-view'

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

const tableViewProps = vi.fn()
vi.mock('@/features/table/table-view', () => ({
  TableView: (props: Record<string, unknown>) => {
    tableViewProps(props)
    return null
  },
}))

describe('StockBalancesGrid row actions', () => {
  it('keeps the shared standard: beyond three actions the rest go into the three-dots menu', () => {
    render(<StockBalancesGrid tableRef={createRef<TableViewHandle>()} onAction={vi.fn()} />)

    expect(tableViewProps).toHaveBeenCalled()
    expect(tableViewProps.mock.lastCall?.[0]).not.toHaveProperty('inlineAllActions')
  })
})
