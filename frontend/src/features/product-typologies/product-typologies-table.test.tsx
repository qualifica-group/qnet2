import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import ProductTypologiesPage from '@/pages/product-typologies-page'
import { ProductTypologiesTable } from '@/features/product-typologies/product-typologies-table'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * The generic `<TableView>` (AG Grid + SSRM) and the app chrome (`PageHeader`)
 * are framework pieces outside this microtask's ownership: they are stubbed
 * so the suite stays focused on what THIS adapter is responsible for —
 * wiring `<Can>` around the table, mounting `<TableView domain=
 * "product-typologies">`, and the delete flow, including the 409 "in use"
 * guard distinct from 403/generic (D-7, mirrors `TagsTable`).
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

vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'modal',
}))

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

const deleteProductTypologyMock = vi.fn()
vi.mock('@/features/product-typologies/api', () => ({
  deleteProductTypology: (...args: unknown[]) => deleteProductTypologyMock(...args),
  fetchProductTypology: vi.fn(),
}))

const toastSuccessMock = vi.fn()
const toastErrorMock = vi.fn()
vi.mock('sonner', () => ({
  toast: {
    success: (...args: unknown[]) => toastSuccessMock(...args),
    error: (...args: unknown[]) => toastErrorMock(...args),
  },
}))

const DELETE_ACTION: TableActionDefinition = {
  key: 'delete',
  label: 'actions.delete',
  icon: 'trash',
  type: 'danger',
  confirm: true,
}

const ROW: TableRow = { id: 1, actions: ['delete'], name: 'Kilogram' }

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<
    { refresh: () => void },
    { domain: string; onAction?: (action: TableActionDefinition, row: TableRow) => void }
  >(function TableViewStub({ domain, onAction }, ref) {
    useImperativeHandle(ref, () => ({ refresh: () => {} }))
    return (
      <div role="region" aria-label={`table-${domain}`}>
        <button type="button" onClick={() => onAction?.(DELETE_ACTION, ROW)}>
          delete row
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
        <ProductTypologiesPage />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <ProductTypologiesTable />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
  canMock.mockReturnValue(true)
  deleteProductTypologyMock.mockReset()
  toastSuccessMock.mockReset()
  toastErrorMock.mockReset()
})

describe('ProductTypologiesPage — permission gating', () => {
  it('shows the forbidden fallback and does not mount the table without viewAny', () => {
    canMock.mockReturnValue(false)

    renderPage()

    expect(
      screen.getByText('You do not have permission to view product typologies.'),
    ).toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'table-product-typologies' })).not.toBeInTheDocument()
  })

  it('mounts <TableView domain="product-typologies"> with viewAny', () => {
    canMock.mockImplementation((permission) => permission === 'product-typologies.viewAny')

    renderPage()

    expect(screen.getByRole('region', { name: 'table-product-typologies' })).toBeInTheDocument()
    expect(
      screen.queryByText('You do not have permission to view product typologies.'),
    ).not.toBeInTheDocument()
  })
})

describe('ProductTypologiesTable — new button (AC-030)', () => {
  it('shows "New product typology" with create, hides it without', () => {
    canMock.mockImplementation((permission) => permission === 'product-typologies.create')

    renderTable()

    expect(screen.getByRole('button', { name: 'New product typology' })).toBeInTheDocument()
  })

  it('hides the create button without the permission', () => {
    canMock.mockReturnValue(false)

    renderTable()

    expect(screen.queryByRole('button', { name: 'New product typology' })).not.toBeInTheDocument()
  })
})

describe('ProductTypologiesTable — delete (D-8: 409 "in use" guard)', () => {
  it('shows the success toast and refreshes on a successful delete', async () => {
    deleteProductTypologyMock.mockResolvedValue(undefined)

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() => expect(deleteProductTypologyMock).toHaveBeenCalledWith(1))
    await waitFor(() =>
      expect(toastSuccessMock).toHaveBeenCalledWith('Product typology deleted successfully.'),
    )
  })

  it('shows a forbidden toast on a 403, using the backend re-authorization, not the UI gate', async () => {
    deleteProductTypologyMock.mockRejectedValue(
      new AxiosError('Forbidden', '403', undefined, undefined, {
        status: 403,
        data: { success: false, message: 'Forbidden' },
      } as never),
    )

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() =>
      expect(toastErrorMock).toHaveBeenCalledWith('You cannot delete this product typology.'),
    )
  })

  it('shows a distinct "in use" toast on a 409 (D-8: used by a product)', async () => {
    deleteProductTypologyMock.mockRejectedValue(
      new AxiosError('Conflict', '409', undefined, undefined, {
        status: 409,
        data: { success: false, message: 'This product typology is used by a product and cannot be deleted.' },
      } as never),
    )

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() =>
      expect(toastErrorMock).toHaveBeenCalledWith(
        'This product typology cannot be deleted because it is linked to one or more products.',
      ),
    )
  })

  it('falls back to the generic error message on any other failure', async () => {
    deleteProductTypologyMock.mockRejectedValue(new Error('network down'))

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() =>
      expect(toastErrorMock).toHaveBeenCalledWith(
        'Unable to delete the product typology. Please retry.',
      ),
    )
  })
})
