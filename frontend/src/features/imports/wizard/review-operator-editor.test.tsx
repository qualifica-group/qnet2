import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ICellRendererParams, IRowNode } from 'ag-grid-community'
import i18n from '@/i18n'
import '@/features/imports/wizard/i18n'
import {
  ReviewOperatorCell,
  type ReviewOperatorCellParams,
  type ReviewOperatorGridContext,
} from '@/features/imports/wizard/review-operator-editor'
import type { ImportRunRowItem } from '@/features/imports/wizard/types'

/**
 * Per-row operator override cell: shows the row's own operator or a
 * "uses the default" hint, click opens a popup with a user picker
 * precompiled from the row's current override, Applica sends a single PATCH
 * via `context.onApplyOperator`, "Use default" clears the local selection
 * before Applica, and Annulla/close send nothing.
 */

// The stub also surfaces the props the competence filter drives (spec 0110):
// the forwarded `params` and the `disabled`/`empty` state of the picker.
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    value,
    onChange,
    disabled,
    params,
    labels,
  }: {
    value: number | null
    onChange: (value: number | null) => void
    disabled?: boolean
    params?: Record<string, string | number | string[] | number[]>
    labels: { triggerLabel: string; empty: string }
  }) => (
    <button
      type="button"
      aria-label={labels.triggerLabel}
      disabled={disabled}
      data-params={params ? JSON.stringify(params) : ''}
      data-empty={labels.empty}
      onClick={() => onChange(42)}
    >
      {value ?? 'none'}
    </button>
  ),
}))

// `useRequiredCategories` (spec 0110 AC-042) resolves the row's own
// requirement through this endpoint; every test drives it explicitly.
const fetchRequiredCategoriesMock = vi.fn()
vi.mock('@/features/assignment/api', () => ({
  fetchRequiredCategories: (...args: unknown[]) => fetchRequiredCategoriesMock(...args),
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

function rowItem(overrides: Partial<ImportRunRowItem> = {}): ImportRunRowItem {
  return {
    id: 10,
    row_number: 1,
    status: 'valid',
    is_edited: false,
    duplicate_of_id: null,
    operator_id: null,
    operator: null,
    operational_site_id: null,
    operational_site: null,
    product_ids: null,
    products: [],
    values: {},
    messages: [],
    ...overrides,
  }
}

beforeEach(() => {
  vi.clearAllMocks()
  fetchRequiredCategoriesMock.mockResolvedValue([])
})

/** Most tests aren't about the default-hint bug: default a global default id so the hint text is stable. */
const DEFAULT_GLOBAL_OPERATOR_ID = 7

/** The run the staged rows belong to; scopes the per-row competence lookup. */
const IMPORT_RUN_ID = 55

/** One client per test (never per render), so the cache never leaks across cases. */
function withQueryClient(ui: ReactNode) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return <QueryClientProvider client={client}>{ui}</QueryClientProvider>
}

function renderCell(overrides: Partial<ReviewOperatorCellParams> = {}) {
  const node = { setData: vi.fn() } as unknown as IRowNode<ImportRunRowItem>
  const onApplyOperator = vi.fn().mockResolvedValue(undefined)
  const context: ReviewOperatorGridContext = {
    onApplyOperator,
    globalDefaultOperatorId: DEFAULT_GLOBAL_OPERATOR_ID,
    importRunId: IMPORT_RUN_ID,
  }
  render(
    withQueryClient(
      <ReviewOperatorCell
        {...({
          data: rowItem(),
          node,
          context,
          ...overrides,
        } as ReviewOperatorCellParams & ICellRendererParams)}
      />,
    ),
  )
  return { node, onApplyOperator }
}

describe('ReviewOperatorCell', () => {
  it('shows the default hint and opens a popup precompiled from the row override when the run has a global default operator', () => {
    renderCell()
    expect(screen.getByRole('button', { name: 'Edit operator' })).toHaveTextContent('Default operator')

    fireEvent.click(screen.getByRole('button', { name: 'Edit operator' }))

    expect(screen.getByRole('dialog')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Operator' })).toHaveTextContent('none')
  })

  it('shows an empty placeholder, not the default hint, when the run has no global default operator', () => {
    renderCell({
      context: {
        onApplyOperator: vi.fn().mockResolvedValue(undefined),
        globalDefaultOperatorId: null,
        importRunId: IMPORT_RUN_ID,
      },
    })
    expect(screen.getByRole('button', { name: 'Edit operator' })).toHaveTextContent('—')
    expect(screen.queryByText('Default operator')).not.toBeInTheDocument()
  })

  it('shows an empty placeholder in readOnly mode too when the run has no global default operator', () => {
    renderCell({
      readOnly: true,
      context: {
        onApplyOperator: vi.fn().mockResolvedValue(undefined),
        globalDefaultOperatorId: null,
        importRunId: IMPORT_RUN_ID,
      },
    })
    expect(screen.getByText('—')).toBeInTheDocument()
    expect(screen.queryByText('Default operator')).not.toBeInTheDocument()
  })

  it("shows the row's own operator name when overridden", () => {
    renderCell({ data: rowItem({ operator_id: 5, operator: { id: 5, name: 'Mario Rossi' } }) })
    expect(screen.getByRole('button', { name: 'Edit operator' })).toHaveTextContent('Mario Rossi')
  })

  it('sends a single PATCH via context.onApplyOperator with the picked id, then closes', async () => {
    const { node, onApplyOperator } = renderCell()

    fireEvent.click(screen.getByRole('button', { name: 'Edit operator' }))
    // The picker only opens up once the row's competence requirement is
    // resolved (spec 0110 AC-043); before that it is deliberately disabled.
    await waitFor(() => expect(screen.getByRole('button', { name: 'Operator' })).toBeEnabled())
    fireEvent.click(screen.getByRole('button', { name: 'Operator' }))
    fireEvent.click(screen.getByRole('button', { name: 'Apply' }))

    await waitFor(() => expect(onApplyOperator).toHaveBeenCalledWith(rowItem(), 42, node))
    expect(onApplyOperator).toHaveBeenCalledTimes(1)
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
  })

  it('"Use default" clears the local selection so Applica sends `null`', async () => {
    const overriddenRow = rowItem({ operator_id: 5, operator: { id: 5, name: 'Mario Rossi' } })
    const { node, onApplyOperator } = renderCell({ data: overriddenRow })

    fireEvent.click(screen.getByRole('button', { name: 'Edit operator' }))
    fireEvent.click(screen.getByRole('button', { name: 'Use default' }))
    fireEvent.click(screen.getByRole('button', { name: 'Apply' }))

    await waitFor(() => expect(onApplyOperator).toHaveBeenCalledWith(overriddenRow, null, node))
  })

  it('Annulla closes the popup without calling onApplyOperator', () => {
    const { onApplyOperator } = renderCell()

    fireEvent.click(screen.getByRole('button', { name: 'Edit operator' }))
    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))

    expect(onApplyOperator).not.toHaveBeenCalled()
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('renders plain text with no popup affordance in readOnly mode', () => {
    renderCell({ readOnly: true })

    expect(screen.getByText('Default operator')).toBeInTheDocument()
    expect(screen.queryByRole('button')).not.toBeInTheDocument()
  })

  it('shows an accessible error and keeps the popup open when the PATCH fails', async () => {
    const onApplyOperator = vi.fn().mockRejectedValue({ isAxiosError: true, response: { status: 422 } })
    const node = { setData: vi.fn() } as unknown as IRowNode<ImportRunRowItem>
    render(
      withQueryClient(
        <ReviewOperatorCell
          {...({
            data: rowItem(),
            node,
            context: {
              onApplyOperator,
              globalDefaultOperatorId: DEFAULT_GLOBAL_OPERATOR_ID,
              importRunId: IMPORT_RUN_ID,
            },
          } as ReviewOperatorCellParams & ICellRendererParams)}
        />,
      ),
    )

    fireEvent.click(screen.getByRole('button', { name: 'Edit operator' }))
    fireEvent.click(screen.getByRole('button', { name: 'Apply' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Some values are not valid. Please check and try again.')
    expect(screen.getByRole('dialog')).toBeInTheDocument()
    expect(node.setData).not.toHaveBeenCalled()
  })
})

/**
 * Spec 0110 AC-042/AC-043: the per-row picker only proposes the users
 * competent for THAT row, resolved from the row's own requirement — and only
 * while the popup is open, never once per rendered row.
 */
describe('ReviewOperatorCell — competence filter (spec 0110)', () => {
  it('resolves the requirement of that row alone, and only once the popup is open', async () => {
    fetchRequiredCategoriesMock.mockResolvedValue([4, 9])
    renderCell({ data: rowItem({ id: 31 }) })

    expect(fetchRequiredCategoriesMock).not.toHaveBeenCalled()

    fireEvent.click(screen.getByRole('button', { name: 'Edit operator' }))

    await waitFor(() =>
      expect(fetchRequiredCategoriesMock).toHaveBeenCalledWith({
        domain: 'import_rows',
        import_run_id: IMPORT_RUN_ID,
        select_all: false,
        row_ids: [31],
      }),
    )
  })

  it('forwards the resolved categories to the picker', async () => {
    fetchRequiredCategoriesMock.mockResolvedValue([4, 9])
    renderCell()

    fireEvent.click(screen.getByRole('button', { name: 'Edit operator' }))

    await waitFor(() =>
      expect(screen.getByRole('button', { name: 'Operator' })).toHaveAttribute(
        'data-params',
        JSON.stringify({ competence_category_ids: [4, 9] }),
      ),
    )
  })

  it('applies no filter when the row expresses no requirement (AC-041 empty union)', async () => {
    renderCell()

    fireEvent.click(screen.getByRole('button', { name: 'Edit operator' }))

    await waitFor(() => expect(fetchRequiredCategoriesMock).toHaveBeenCalledTimes(1))
    expect(screen.getByRole('button', { name: 'Operator' })).toHaveAttribute('data-params', '')
    expect(screen.getByRole('button', { name: 'Operator' })).toHaveAttribute('data-empty', 'No results.')
  })

  // Only the PICKER waits on the lookup: Applica also commits "use the run
  // default" (a `null` operator), which the competence filter never governs,
  // so gating it would take that action away for the length of a request.
  it('keeps the picker disabled while the requirement is still resolving (AC-043)', async () => {
    let resolveCategories: (ids: number[]) => void = () => {}
    fetchRequiredCategoriesMock.mockReturnValue(
      new Promise<number[]>((resolve) => {
        resolveCategories = resolve
      }),
    )
    renderCell()

    fireEvent.click(screen.getByRole('button', { name: 'Edit operator' }))

    expect(screen.getByRole('button', { name: 'Operator' })).toBeDisabled()
    expect(screen.getByRole('button', { name: 'Apply' })).toBeEnabled()

    resolveCategories([4])
    await waitFor(() => expect(screen.getByRole('button', { name: 'Operator' })).toBeEnabled())
  })

  it('announces an explicit competence empty state once the filter applies (AC-043)', async () => {
    fetchRequiredCategoriesMock.mockResolvedValue([4])
    renderCell()

    fireEvent.click(screen.getByRole('button', { name: 'Edit operator' }))

    await waitFor(() =>
      expect(screen.getByRole('button', { name: 'Operator' })).toHaveAttribute(
        'data-empty',
        'No operator is competent for this row.',
      ),
    )
  })
})
