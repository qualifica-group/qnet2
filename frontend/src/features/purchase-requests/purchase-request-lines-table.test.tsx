import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import type { BulkAction, TableSelection } from '@/features/table/use-bulk-actions-slot'
import { PurchaseRequestLinesTable } from '@/features/purchase-requests/purchase-request-lines-table'

const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: canMock, hasRole: () => false, roles: [], isLoading: false }),
}))

let selection: TableSelection = { ids: [], rows: [] }
vi.mock('@/features/table/table-view', () => ({
  TableView: ({ getBulkActions }: { getBulkActions: (s: TableSelection) => BulkAction[] }) => (
    <ul>
      {getBulkActions(selection).map((action) => (
        <li key={action.key}>
          <button type="button" disabled={action.disabled} title={action.disabledReason}>
            {action.label}
          </button>
        </li>
      ))}
    </ul>
  ),
}))
vi.mock('@/components/page-header', () => ({ PageHeader: () => null }))

function row(id: number, overrides: Record<string, unknown> = {}) {
  return { id, status: 'approved', supplier: { id: 12, name: 'Rossi Srl' }, purchase_order: null, ...overrides }
}

function renderTable(rows: Record<string, unknown>[], allowed = true) {
  canMock.mockImplementation((permission) => allowed && permission === 'purchase-orders.create')
  selection = { ids: rows.map((r) => Number(r.id)), rows: rows as TableSelection['rows'] }
  render(
    <QueryClientProvider client={new QueryClient()}>
      <MemoryRouter>
        <PurchaseRequestLinesTable />
      </MemoryRouter>
    </QueryClientProvider>,
  )
  return screen.getByRole('button', { name: 'Generate ODA' })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('Generate ODA bulk action (spec 0210 AC-022)', () => {
  it('is enabled for approved lines with a supplier when the actor can create orders', () => {
    expect(renderTable([row(1), row(2)])).toBeEnabled()
  })

  it.each([
    ['a line is not approved', [row(1), row(2, { status: 'ordered' })], true, 'Only Approved lines can generate an ODA.'],
    ['a line has no supplier', [row(1, { supplier: null })], true, 'Every selected line needs a supplier: assign it first.'],
    ['a line is already linked', [row(1, { purchase_order: { id: 9, code: 'ODA-9' } })], true, 'Some selected lines already belong to an ODA.'],
    ['the actor lacks the permission', [row(1)], false, 'You do not have the permission to create purchase orders.'],
  ])('is disabled with the reason when %s', (_case, rows, allowed, reason) => {
    const button = renderTable(rows, allowed)
    expect(button).toBeDisabled()
    expect(button).toHaveAttribute('title', reason)
  })
})
