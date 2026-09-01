import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import UnitsOfMeasurePage from '@/pages/units-of-measure-page'
import { UnitsOfMeasureTable } from '@/features/units-of-measure/units-of-measure-table'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * The generic `<TableView>` (AG Grid + SSRM) and the app chrome (`PageHeader`)
 * are framework pieces outside this microtask's ownership: they are stubbed
 * so the suite stays focused on what THIS adapter is responsible for —
 * wiring `<Can>` around the table, mounting `<TableView domain=
 * "units-of-measure">`, and the delete flow, including the 409 "in use"
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

const deleteUnitOfMeasureMock = vi.fn()
vi.mock('@/features/units-of-measure/api', () => ({
  deleteUnitOfMeasure: (...args: unknown[]) => deleteUnitOfMeasureMock(...args),
  fetchUnitOfMeasure: vi.fn(),
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
        <UnitsOfMeasurePage />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <UnitsOfMeasureTable />
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
  deleteUnitOfMeasureMock.mockReset()
  toastSuccessMock.mockReset()
  toastErrorMock.mockReset()
})

describe('UnitsOfMeasurePage — permission gating', () => {
  it('shows the forbidden fallback and does not mount the table without viewAny', () => {
    canMock.mockReturnValue(false)

    renderPage()

    expect(
      screen.getByText("You don't have permission to view units of measure."),
    ).toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'table-units-of-measure' })).not.toBeInTheDocument()
  })

  it('mounts <TableView domain="units-of-measure"> with viewAny', () => {
    canMock.mockImplementation((permission) => permission === 'units-of-measure.viewAny')

    renderPage()

    expect(screen.getByRole('region', { name: 'table-units-of-measure' })).toBeInTheDocument()
    expect(
      screen.queryByText("You don't have permission to view units of measure."),
    ).not.toBeInTheDocument()
  })
})

describe('UnitsOfMeasureTable — new button (AC-030)', () => {
  it('shows "New unit of measure" with create, hides it without', () => {
    canMock.mockImplementation((permission) => permission === 'units-of-measure.create')

    renderTable()

    expect(screen.getByRole('button', { name: 'New unit of measure' })).toBeInTheDocument()
  })

  it('hides the create button without the permission', () => {
    canMock.mockReturnValue(false)

    renderTable()

    expect(screen.queryByRole('button', { name: 'New unit of measure' })).not.toBeInTheDocument()
  })
})

describe('UnitsOfMeasureTable — delete (D-7: 409 "in use" guard)', () => {
  it('shows the success toast and refreshes on a successful delete', async () => {
    deleteUnitOfMeasureMock.mockResolvedValue(undefined)

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() => expect(deleteUnitOfMeasureMock).toHaveBeenCalledWith(1))
    await waitFor(() =>
      expect(toastSuccessMock).toHaveBeenCalledWith('Unit of measure deleted successfully.'),
    )
  })

  it('shows a forbidden toast on a 403, using the backend re-authorization, not the UI gate', async () => {
    deleteUnitOfMeasureMock.mockRejectedValue(
      new AxiosError('Forbidden', '403', undefined, undefined, {
        status: 403,
        data: { success: false, message: 'Forbidden' },
      } as never),
    )

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() =>
      expect(toastErrorMock).toHaveBeenCalledWith('You cannot delete this unit of measure.'),
    )
  })

  it('shows a distinct "in use" toast on a 409 (D-7: used by a product or a quote line)', async () => {
    deleteUnitOfMeasureMock.mockRejectedValue(
      new AxiosError('Conflict', '409', undefined, undefined, {
        status: 409,
        data: { success: false, message: 'This unit of measure is used by a product and cannot be deleted.' },
      } as never),
    )

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() =>
      expect(toastErrorMock).toHaveBeenCalledWith(
        'Cannot delete: the unit of measure is used by a product or a quote line.',
      ),
    )
  })

  it('falls back to the generic error message on any other failure', async () => {
    deleteUnitOfMeasureMock.mockRejectedValue(new Error('network down'))

    renderTable()
    screen.getByRole('button', { name: 'delete row' }).click()

    await waitFor(() =>
      expect(toastErrorMock).toHaveBeenCalledWith(
        'Unable to delete the unit of measure. Please try again.',
      ),
    )
  })
})
