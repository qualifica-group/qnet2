import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle } from 'react'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { FieldChangeRequestsTable } from '@/features/field-change-requests/field-change-requests-table'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * Smoke test of the field-change-requests adapter (spec 0078, AC-036): it
 * mounts `<TableView domain="field-change-requests">` with its column
 * renderer map and no "New" affordance — a request is proposed from its own
 * write-interception dialog, never created from this browse grid — and
 * routes the backend's only row action (`view`) to the detail, the single
 * surface carrying Approve/Reject. `<TableView>` (AG Grid + SSRM) is a
 * framework piece outside this microtask's ownership, stubbed the same way
 * `contracts-table.test.tsx` stubs it; `useModuleOpener` is mocked for the
 * same reason it is there.
 */

vi.mock('@/components/page-header', () => ({
  PageHeader: () => <div />,
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
  type: 'link',
  confirm: false,
}

const ROW: TableRow = {
  id: 12,
  actions: ['view'],
  status: 'pending',
}

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<
    { refresh: () => void },
    { domain: string; onAction?: (action: TableActionDefinition, row: TableRow) => void }
  >(function TableViewStub({ domain, onAction }, ref) {
    useImperativeHandle(ref, () => ({ refresh: () => {} }))
    return (
      <div role="region" aria-label={`table-${domain}`}>
        <button type="button" onClick={() => onAction?.(VIEW_ACTION, ROW)}>
          view row
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
        <FieldChangeRequestsTable />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  openViewMock.mockClear()
})

describe('FieldChangeRequestsTable — domain wiring', () => {
  it('mounts <TableView domain="field-change-requests">', () => {
    renderTable()

    expect(screen.getByRole('region', { name: 'table-field-change-requests' })).toBeInTheDocument()
  })

  it('opens the request detail on the `view` row action', () => {
    renderTable()

    screen.getByRole('button', { name: 'view row' }).click()

    expect(openViewMock).toHaveBeenCalledWith(ROW)
  })
})
