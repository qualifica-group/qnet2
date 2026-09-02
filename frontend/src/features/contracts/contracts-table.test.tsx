import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle } from 'react'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ContractsTable } from '@/features/contracts/contracts-table'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * Spec 0072 D-6: a contract is never created nor deleted by hand — the
 * adapter never renders a "New contract" affordance, unlike every other
 * `*-table.tsx`. `useModuleOpener` is mocked (throws unless `contracts` is
 * registered, a different file's concern); `<TableView>` is stubbed the
 * same way `quotes-table.test.tsx` stubs it.
 */

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: React.ReactNode }) => <div>{actions}</div>,
}))

const openViewMock = vi.fn()
vi.mock('@/features/modules/use-module-opener', () => ({
  useModuleOpener: () => ({
    openCreate: vi.fn(),
    openCreateWith: vi.fn(),
    openView: openViewMock,
    openEdit: vi.fn(),
    openDuplicate: vi.fn(),
    sheet: null,
  }),
}))

const VIEW_ACTION: TableActionDefinition = {
  key: 'view',
  label: 'actions.view',
  icon: 'eye',
  type: 'action',
  confirm: false,
}

const VALIDATE_ACTION: TableActionDefinition = {
  key: 'validate',
  label: 'contracts.actions.validate',
  icon: 'badge-check',
  type: 'action',
  confirm: false,
}

const ROW: TableRow = {
  id: 7,
  actions: ['view', 'activity', 'validate', 'program', 'terminate', 'reactivate', 'edit', 'change_status'],
  title: 'Contratto Acme',
  code: 'QUO-0007',
}

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<
    { refresh: () => void },
    { domain: string; onAction?: (action: TableActionDefinition, row: TableRow) => void }
  >(function TableViewStub({ domain, onAction }, ref) {
    useImperativeHandle(ref, () => ({ refresh: vi.fn() }))
    return (
      <div role="region" aria-label={`table-${domain}`}>
        <button type="button" onClick={() => onAction?.(VIEW_ACTION, ROW)}>
          view row
        </button>
        <button type="button" onClick={() => onAction?.(VALIDATE_ACTION, ROW)}>
          validate row
        </button>
      </div>
    )
  }),
}))

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <ContractsTable />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  openViewMock.mockReset()
})

describe('ContractsTable — domain wiring', () => {
  it('mounts <TableView domain="contracts"> with no client-declared columns', () => {
    renderTable()
    expect(screen.getByRole('region', { name: 'table-contracts' })).toBeInTheDocument()
  })

  it('never renders a "New contract" affordance (D-6)', () => {
    renderTable()
    expect(screen.queryByRole('button', { name: /new contract/i })).not.toBeInTheDocument()
  })

  it('opens the detail view on the "view" row action', () => {
    renderTable()
    screen.getByRole('button', { name: 'view row' }).click()
    expect(openViewMock).toHaveBeenCalledWith(ROW)
  })

  it('opens the detail view on a domain-action row action too (its real dialog lives there, MT-04)', () => {
    renderTable()
    screen.getByRole('button', { name: 'validate row' }).click()
    expect(openViewMock).toHaveBeenCalledWith(ROW)
  })
})
