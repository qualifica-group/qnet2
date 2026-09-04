import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react'
import type { GridReadyEvent, SelectionChangedEvent } from 'ag-grid-community'
import i18n from '@/i18n'
import '@/features/imports/wizard/i18n'
import { ReviewGrid } from '@/features/imports/wizard/review-grid'
import type { ImportRunDetail } from '@/features/imports/wizard/types'

/**
 * Checkbox multi-selection over the SSRM review grid drives a bulk operator
 * assign: the toolbar appears only once AG Grid's own server-side selection
 * state is non-empty, its "Azioni" dropdown's "Assegna operatori" entry opens
 * the SHARED "Assegna operatori" popup (spec 0048 AC-050) whose input maps
 * 1:1 onto the bulk PATCH payload (`buildBulkAssignPayload`, unit-tested in
 * `use-review-rows-assign.test.tsx`), and a successful assign refreshes the
 * SSRM cache and clears the selection. The dropdown's own two-entry contract
 * and the "Assegna prodotti" flow are covered by `review-bulk-assign-bar.test.tsx`.
 * `AgGridReact` is stubbed — this codebase never mounts the real grid in
 * tests (see `data-table.test.tsx`); the stub exposes the grid api AG Grid
 * would otherwise own and lets the test fire `onSelectionChanged` directly.
 */

let capturedProps: Record<string, unknown> = {}

const refreshServerSideMock = vi.fn()
const setServerSideSelectionStateMock = vi.fn()

vi.mock('ag-grid-react', () => ({
  AgGridReact: (props: Record<string, unknown>) => {
    capturedProps = props
    const onGridReady = props.onGridReady as ((event: GridReadyEvent) => void) | undefined
    onGridReady?.({
      api: {
        refreshServerSide: refreshServerSideMock,
        setServerSideSelectionState: setServerSideSelectionStateMock,
      },
    } as unknown as GridReadyEvent)
    return <div data-testid="ag-grid-stub" />
  },
}))

const handleBulkAssignMock = vi.fn()

vi.mock('@/features/imports/wizard/use-review-rows', () => ({
  useReviewRows: () => ({
    datasource: {},
    handleCellValueChanged: vi.fn(),
    handleResolutionChange: vi.fn(),
    handleApplyGeo: vi.fn(),
    handleApplyOperator: vi.fn(),
    handleApplySite: vi.fn(),
    handleApplyProducts: vi.fn(),
    handleBulkAssign: (...args: unknown[]) => handleBulkAssignMock(...args),
    isSaving: false,
    hasSaveError: false,
  }),
  buildBulkAssignPayload: (
    selection: { selectAll: boolean; toggledNodes: string[] },
    input: { operational_site_id: number; mode: 'single' | 'balanced'; operator_id?: number },
  ) => ({
    operational_site_id: input.operational_site_id,
    mode: input.mode,
    ...(input.mode === 'single' ? { operator_id: input.operator_id } : {}),
    select_all: selection.selectAll,
    row_ids: selection.toggledNodes.map(Number),
  }),
  buildBulkAssignProductsPayload: (
    selection: { selectAll: boolean; toggledNodes: string[] },
    productIds: number[],
  ) => ({
    product_ids: productIds,
    select_all: selection.selectAll,
    row_ids: selection.toggledNodes.map(Number),
  }),
}))

// `useReviewProductsScope` calls the real `useForSelectLabels` (a TanStack
// Query hook) — this file never wraps `ReviewGrid` in a `QueryClientProvider`
// (the whole `useReviewRows` module is mocked out above for the same
// reason), so it is stubbed here too. Its own contract is covered by
// `use-review-products-scope.test.ts`.
vi.mock('@/features/imports/wizard/use-review-products-scope', () => ({
  useReviewProductsScope: () => ({ globalDefaultProductIds: [], campaignCategoryIds: [] }),
}))

const SITE_PICK_ID = 84
const OPERATOR_PICK_ID = 42

vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    resource,
    value,
    onChange,
    labels,
  }: {
    resource: string
    value: number | null
    onChange: (value: number | null) => void
    labels: { triggerLabel: string }
  }) => (
    <button
      type="button"
      aria-label={labels.triggerLabel}
      onClick={() => onChange(resource === 'operational-sites' ? SITE_PICK_ID : OPERATOR_PICK_ID)}
    >
      {value ?? 'none'}
    </button>
  ),
}))

function baseRun(overrides: Partial<ImportRunDetail> = {}): ImportRunDetail {
  return {
    id: 7,
    resource: 'leads',
    status: 'reviewing',
    original_filename: 'leads.csv',
    total_rows: 10,
    valid_rows: 8,
    warning_rows: 1,
    error_rows: 1,
    duplicate_rows: 0,
    imported_rows: null,
    modified_rows: 0,
    has_error_report: false,
    created_at: '2026-07-15T00:00:00Z',
    error_count: 0,
    detected_columns: [{ key: 'Email', name: 'Email', index: 0, duplicate: false }],
    column_mapping: { Email: 'email' },
    global_config: null,
    dedup_strategy: null,
    suggested_mapping: null,
    fields: [{ id: 'email', label: 'Email', required: true, group: 'contact', type: 'string' }],
    global_fields: [],
    dedup_modes: ['create_new'],
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  capturedProps = {}
  refreshServerSideMock.mockReset()
  setServerSideSelectionStateMock.mockReset()
  handleBulkAssignMock.mockReset()
})

/**
 * `selectedSiteIds` seeds a `forEachNode` stub with one selected node per
 * entry (AC-031's site-precompile reads `node.data.operational_site_id` off
 * exactly that); omitted by every pre-existing test, which does not care
 * about the popup's precompiled Sede.
 */
function fireSelectionChanged(
  state: { selectAll: boolean; toggledNodes: string[] } | null,
  selectedSiteIds: Array<number | null> = [],
) {
  const onSelectionChanged = capturedProps.onSelectionChanged as
    | ((event: SelectionChangedEvent) => void)
    | undefined
  const forEachNode = (
    callback: (node: { isSelected: () => boolean; data: { operational_site_id: number | null } }) => void,
  ) => {
    selectedSiteIds.forEach((operational_site_id) => callback({ isSelected: () => true, data: { operational_site_id } }))
  }
  act(() => {
    onSelectionChanged?.({
      api: { getServerSideSelectionState: () => state, forEachNode },
    } as unknown as SelectionChangedEvent)
  })
}

function openAssignPopup() {
  // Radix' DropdownMenu trigger opens on `pointerdown`, not `click`.
  fireEvent.pointerDown(screen.getByRole('button', { name: /^Actions/ }), { button: 0, ctrlKey: false })
  fireEvent.click(screen.getByRole('menuitem', { name: 'Assign operators' }))
}

describe('ReviewGrid — bulk assign via the shared popup', () => {
  it('hides the bulk-assign bar with no selection', () => {
    render(<ReviewGrid domain="leads" run={baseRun()} />)
    expect(screen.queryByRole('toolbar')).not.toBeInTheDocument()
  })

  it('shows the bar once the selection is non-empty (partial selection)', () => {
    render(<ReviewGrid domain="leads" run={baseRun()} />)
    fireSelectionChanged({ selectAll: false, toggledNodes: ['1', '2'] })
    expect(screen.getByRole('toolbar')).toBeInTheDocument()
    expect(screen.getByText('2 row(s) selected')).toBeInTheDocument()
  })

  it('sends select_all: false, mode "balanced" with the included row ids for a partial selection', async () => {
    handleBulkAssignMock.mockResolvedValue({ updated: 2 })
    render(<ReviewGrid domain="leads" run={baseRun()} />)
    fireSelectionChanged({ selectAll: false, toggledNodes: ['1', '2'] })

    openAssignPopup()
    fireEvent.click(screen.getByRole('radio', { name: 'Balanced split' }))
    fireEvent.click(screen.getByRole('button', { name: 'Site' }))
    fireEvent.click(screen.getByRole('button', { name: 'Assign' }))

    await waitFor(() =>
      expect(handleBulkAssignMock).toHaveBeenCalledWith({
        operational_site_id: SITE_PICK_ID,
        mode: 'balanced',
        select_all: false,
        row_ids: [1, 2],
      }),
    )
  })

  it('sends select_all: true with the excluded row ids for a select-all selection', async () => {
    handleBulkAssignMock.mockResolvedValue({ updated: 40 })
    render(<ReviewGrid domain="leads" run={baseRun()} />)
    fireSelectionChanged({ selectAll: true, toggledNodes: ['5'] })

    openAssignPopup()
    fireEvent.click(screen.getByRole('radio', { name: 'Balanced split' }))
    fireEvent.click(screen.getByRole('button', { name: 'Site' }))
    fireEvent.click(screen.getByRole('button', { name: 'Assign' }))

    await waitFor(() =>
      expect(handleBulkAssignMock).toHaveBeenCalledWith({
        operational_site_id: SITE_PICK_ID,
        mode: 'balanced',
        select_all: true,
        row_ids: [5],
      }),
    )
  })

  it('sends operator_id alongside operational_site_id for mode "single"', async () => {
    handleBulkAssignMock.mockResolvedValue({ updated: 2 })
    render(<ReviewGrid domain="leads" run={baseRun()} />)
    fireSelectionChanged({ selectAll: false, toggledNodes: ['1', '2'] })

    openAssignPopup()
    fireEvent.click(screen.getByRole('radio', { name: 'Assign to operator' }))
    fireEvent.click(screen.getByRole('button', { name: 'Site' }))
    fireEvent.click(screen.getByRole('button', { name: 'Operator' }))
    fireEvent.click(screen.getByRole('button', { name: 'Assign' }))

    await waitFor(() =>
      expect(handleBulkAssignMock).toHaveBeenCalledWith({
        operational_site_id: SITE_PICK_ID,
        mode: 'single',
        operator_id: OPERATOR_PICK_ID,
        select_all: false,
        row_ids: [1, 2],
      }),
    )
  })

  it('refreshes the SSRM cache and clears the selection after a successful assign', async () => {
    handleBulkAssignMock.mockResolvedValue({ updated: 2 })
    render(<ReviewGrid domain="leads" run={baseRun()} />)
    fireSelectionChanged({ selectAll: false, toggledNodes: ['1', '2'] })

    openAssignPopup()
    fireEvent.click(screen.getByRole('radio', { name: 'Balanced split' }))
    fireEvent.click(screen.getByRole('button', { name: 'Site' }))
    fireEvent.click(screen.getByRole('button', { name: 'Assign' }))

    await waitFor(() =>
      expect(setServerSideSelectionStateMock).toHaveBeenCalledWith({ selectAll: false, toggledNodes: [] }),
    )
    expect(refreshServerSideMock).toHaveBeenCalledWith({ purge: true })
  })

  it('never wires selection in readOnly mode, so the bar never appears', () => {
    render(<ReviewGrid domain="leads" run={baseRun()} readOnly />)
    fireSelectionChanged({ selectAll: false, toggledNodes: ['1'] })
    expect(screen.queryByRole('toolbar')).not.toBeInTheDocument()
  })
})

describe('ReviewGrid — popup Sede precompile (AC-031)', () => {
  it('precompiles the Sede when every selected row shares one', () => {
    render(<ReviewGrid domain="leads" run={baseRun()} />)
    fireSelectionChanged({ selectAll: false, toggledNodes: ['1', '2'] }, [5, 5])

    openAssignPopup()
    fireEvent.click(screen.getByRole('radio', { name: 'Balanced split' }))
    expect(screen.getByRole('button', { name: 'Site' })).toHaveTextContent('5')
  })

  it('leaves the Sede unset when the selected rows have different sites', () => {
    render(<ReviewGrid domain="leads" run={baseRun()} />)
    fireSelectionChanged({ selectAll: false, toggledNodes: ['1', '2'] }, [5, 8])

    openAssignPopup()
    fireEvent.click(screen.getByRole('radio', { name: 'Balanced split' }))
    expect(screen.getByRole('button', { name: 'Site' })).toHaveTextContent('none')
  })

  it('skips the precompile for a select-all selection (no cheap shared-site read)', () => {
    render(<ReviewGrid domain="leads" run={baseRun()} />)
    fireSelectionChanged({ selectAll: true, toggledNodes: ['9'] }, [5, 5])

    openAssignPopup()
    fireEvent.click(screen.getByRole('radio', { name: 'Balanced split' }))
    expect(screen.getByRole('button', { name: 'Site' })).toHaveTextContent('none')
  })
})
