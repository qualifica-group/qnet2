import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { RegistriesTable } from '@/features/registries/registries-table'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * AC-A1 (spec 0022) — the Registries adapter no longer opens a Sheet: the view
 * and edit row actions and the "New" button navigate to the dedicated pages.
 * The generic `<TableView>` and the app chrome are stubbed; the stub exposes
 * the row actions as buttons so the adapter's `onAction` wiring runs for real.
 */
const canMock = vi.fn<(permission: string) => boolean>()
// Default open mode for registries (page, spec 0022/0042).
vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'page',
}))

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

const documentsSectionMock = vi.fn()
vi.mock('@/features/attachments/documents-section', () => ({
  DocumentsSection: (props: { resource: string; id: number; canUpload: boolean; canDelete: boolean }) => {
    documentsSectionMock(props)
    return <div>{`documents-section:${props.resource}:${props.id}`}</div>
  },
}))

const refreshMock = vi.fn()
const setFilterModelMock = vi.fn()

const ROW: TableRow = { id: 12, actions: ['view', 'edit'] }
const action = (key: string): TableActionDefinition => ({
  key,
  label: key,
  icon: key,
  type: 'action',
  confirm: false,
})

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<
    { refresh: () => void; setFilterModel: (patch: Record<string, unknown>) => void },
    {
      domain: string
      onAction: RowActionHandler
      onFilterModelChange?: (model: Record<string, unknown>) => void
    }
  >(function TableViewStub({ domain, onAction, onFilterModelChange }, ref) {
    useImperativeHandle(ref, () => ({ refresh: refreshMock, setFilterModel: setFilterModelMock }))
    return (
      <div role="region" aria-label={`table-${domain}`}>
        <button
          type="button"
          onClick={() =>
            onFilterModelChange?.({ registry_type: { filterType: 'set', values: ['company'] } })
          }
        >
          grid-filters-companies
        </button>
        <button type="button" onClick={() => onAction(action('view'), ROW)}>
          row-view
        </button>
        <button type="button" onClick={() => onAction(action('edit'), ROW)}>
          row-edit
        </button>
        <button type="button" onClick={() => onAction(action('documents'), ROW)}>
          row-documents
        </button>
      </div>
    )
  }),
}))

function LocationProbe() {
  const { pathname } = useLocation()
  return <span>location:{pathname}</span>
}

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={['/registries']}>
        <LocationProbe />
        <Routes>
          <Route path="/registries" element={<RegistriesTable />} />
          <Route path="*" element={null} />
        </Routes>
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
  refreshMock.mockReset()
  setFilterModelMock.mockReset()
  documentsSectionMock.mockReset()
})

describe('RegistriesTable — navigation to the dedicated pages (AC-A1)', () => {
  it('navigates to the detail page on the view row action', async () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'row-view' }))

    expect(screen.getByText('location:/registries/12')).toBeInTheDocument()
  })

  it('ignores the retired edit row action: editing starts from the detail page', async () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'row-edit' }))

    expect(screen.getByText('location:/registries')).toBeInTheDocument()
  })

  it('navigates to the create page from the New registry button', async () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: /new registry/i }))

    expect(screen.getByText('location:/registries/new')).toBeInTheDocument()
  })

  it('hides the New registry button without registries.create', () => {
    canMock.mockImplementation((permission) => permission !== 'registries.create')

    renderTable()

    expect(screen.queryByRole('button', { name: /new registry/i })).not.toBeInTheDocument()
  })
})

describe('RegistriesTable — "documents" row action (spec 0173)', () => {
  it('opens the documents dialog on the registry attachable alias', () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'row-documents' }))

    expect(screen.getByRole('dialog')).toBeInTheDocument()
    expect(screen.getByText('documents-section:registry:12')).toBeInTheDocument()
    expect(documentsSectionMock).toHaveBeenCalledWith(
      expect.objectContaining({ resource: 'registry', id: 12, canUpload: true, canDelete: true }),
    )
  })

  it('refreshes the grid when the dialog closes, so the documents badge stays current', () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'row-documents' }))
    fireEvent.keyDown(screen.getByRole('dialog'), { key: 'Escape' })

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(refreshMock).toHaveBeenCalledTimes(1)
  })
})

describe('RegistriesTable — individuals / companies tabs', () => {
  it('starts on All and writes the registry_type filter through the grid handle', () => {
    renderTable()

    expect(screen.getByRole('tab', { name: 'All' })).toHaveAttribute('aria-selected', 'true')

    fireEvent.click(screen.getByRole('tab', { name: 'Individuals' }))
    expect(setFilterModelMock).toHaveBeenLastCalledWith({
      registry_type: { filterType: 'set', values: ['individual'] },
    })

    fireEvent.click(screen.getByRole('tab', { name: 'All' }))
    expect(setFilterModelMock).toHaveBeenLastCalledWith({ registry_type: null })
  })

  it('follows the live grid filter model', () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'grid-filters-companies' }))

    expect(screen.getByRole('tab', { name: 'Companies' })).toHaveAttribute('aria-selected', 'true')
    expect(screen.getByRole('tab', { name: 'All' })).toHaveAttribute('aria-selected', 'false')
  })
})
