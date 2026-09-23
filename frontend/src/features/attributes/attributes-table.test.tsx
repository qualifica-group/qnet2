import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import AttributesPage from '@/pages/attributes-page'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * AC-021 — permission gating of the Attributes page. The generic
 * `<TableView>` (AG Grid + SSRM) and the app chrome (`PageHeader`) are
 * framework pieces outside this microtask's ownership: they are stubbed so
 * the suite stays focused on what THIS adapter is responsible for — wiring
 * `<Can>` around the table and mounting `<TableView domain="attributes">`
 * with the right domain (mirrors `ReferentTypesPage`'s suite).
 */
const canMock = vi.fn<(permission: string) => boolean>()

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({
    can: (permission: string) => canMock(permission),
    hasRole: () => false,
    roles: [],
    isLoading: false,
  }),
}))

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

// This suite exercises the default modal behaviour; force the resolved open
// mode so it never depends on an AuthProvider (spec 0042).
vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'modal',
}))

// Never settles: the duplicate test only asserts which source is fetched.
const fetchAttributeMock = vi.fn<(id: number) => Promise<never>>(() => new Promise(() => {}))

vi.mock('@/features/attributes/api', () => ({
  fetchAttribute: (id: number) => fetchAttributeMock(id),
  deleteAttribute: vi.fn(),
}))

const SOURCE_ROW: TableRow = { id: 7, actions: ['duplicate'], code: 'color' }
const DUPLICATE_ACTION: TableActionDefinition = {
  key: 'duplicate',
  label: 'actions.duplicate',
  icon: 'copy',
  type: 'action',
  confirm: false,
}

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<
    { refresh: () => void },
    { domain: string; onAction: (action: TableActionDefinition, row: TableRow) => void }
  >(function TableViewStub({ domain, onAction }, ref) {
    useImperativeHandle(ref, () => ({ refresh: () => {} }))
    return (
      <div role="region" aria-label={`table-${domain}`}>
        <button type="button" onClick={() => onAction(DUPLICATE_ACTION, SOURCE_ROW)}>
          row-duplicate
        </button>
      </div>
    )
  }),
}))

function renderPage() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <AttributesPage />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
})

describe('AttributesPage — permission gating (AC-021)', () => {
  it('shows the forbidden fallback and does not mount the table without viewAny', () => {
    canMock.mockReturnValue(false)

    renderPage()

    expect(screen.getByText("You don't have permission to view attributes.")).toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'table-attributes' })).not.toBeInTheDocument()
  })

  it('mounts <TableView domain="attributes"> with viewAny', () => {
    canMock.mockImplementation((permission) => permission === 'attributes.viewAny')

    renderPage()

    expect(screen.getByRole('region', { name: 'table-attributes' })).toBeInTheDocument()
    expect(
      screen.queryByText("You don't have permission to view attributes."),
    ).not.toBeInTheDocument()
  })
})

describe('AttributesPage — "duplicate" row action', () => {
  beforeEach(() => {
    canMock.mockReturnValue(true)
    fetchAttributeMock.mockClear()
  })

  it('opens the create sheet loading the clicked row as the source', async () => {
    renderPage()

    fireEvent.click(screen.getByRole('button', { name: 'row-duplicate' }))

    expect(await screen.findByRole('dialog', { name: 'Create attribute' })).toBeInTheDocument()
    await waitFor(() => expect(fetchAttributeMock).toHaveBeenCalledWith(7))
  })
})
