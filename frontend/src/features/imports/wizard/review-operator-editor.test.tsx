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

// `useAssignmentScope` (spec 0110 AC-042, 0113 AC-032) resolves the row's own
// Sede + requirement through this endpoint; every test drives it explicitly.
const fetchAssignmentScopeMock = vi.fn()
vi.mock('@/features/assignment/api', () => ({
  fetchAssignmentScope: (...args: unknown[]) => fetchAssignmentScopeMock(...args),
}))

/** Envelope `data` of `POST /assignment/selection-scope`, spelled once. */
function scope(
  overrides: Partial<{
    product_category_ids: number[]
    operational_site_id: number | null
    campaign_ids: number[]
  }> = {},
) {
  return { product_category_ids: [], operational_site_id: null, campaign_ids: [], ...overrides }
}

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
  fetchAssignmentScopeMock.mockResolvedValue(scope())
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
 * Spec 0110 AC-042/AC-043 and spec 0113 AC-032/AC-034: the per-row picker
 * only proposes the users of the Sede that row's campaign resolves to who are
 * competent for it, resolved from the row's own scope — and only while the
 * popup is open, never once per rendered row.
 */
describe('ReviewOperatorCell — Sede + competence filter (spec 0110, 0113)', () => {
  it('resolves the scope of that row alone, and only once the popup is open', async () => {
    fetchAssignmentScopeMock.mockResolvedValue(scope({ product_category_ids: [4, 9] }))
    renderCell({ data: rowItem({ id: 31 }) })

    expect(fetchAssignmentScopeMock).not.toHaveBeenCalled()

    fireEvent.click(screen.getByRole('button', { name: 'Edit operator' }))

    await waitFor(() =>
      expect(fetchAssignmentScopeMock).toHaveBeenCalledWith({
        domain: 'import_rows',
        import_run_id: IMPORT_RUN_ID,
        select_all: false,
        row_ids: [31],
      }),
    )
  })

  // AC-032: the row's Sede narrows the picker exactly like its categories do.
  it('forwards both the resolved Sede and the resolved categories to the picker', async () => {
    fetchAssignmentScopeMock.mockResolvedValue(
      scope({ product_category_ids: [4, 9], operational_site_id: 84 }),
    )
    renderCell()

    fireEvent.click(screen.getByRole('button', { name: 'Edit operator' }))

    await waitFor(() =>
      expect(screen.getByRole('button', { name: 'Operator' })).toHaveAttribute(
        'data-params',
        JSON.stringify({ operational_site_id: 84, competence_category_ids: [4, 9] }),
      ),
    )
  })

  it('filters by Sede alone when the row expresses no competence requirement', async () => {
    fetchAssignmentScopeMock.mockResolvedValue(scope({ operational_site_id: 84 }))
    renderCell()

    fireEvent.click(screen.getByRole('button', { name: 'Edit operator' }))

    await waitFor(() =>
      expect(screen.getByRole('button', { name: 'Operator' })).toHaveAttribute(
        'data-params',
        JSON.stringify({ operational_site_id: 84 }),
      ),
    )
  })

  it('applies no filter when the row has neither a Sede nor a requirement', async () => {
    renderCell()

    fireEvent.click(screen.getByRole('button', { name: 'Edit operator' }))

    await waitFor(() => expect(fetchAssignmentScopeMock).toHaveBeenCalledTimes(1))
    expect(screen.getByRole('button', { name: 'Operator' })).toHaveAttribute('data-params', '')
    expect(screen.getByRole('button', { name: 'Operator' })).toHaveAttribute('data-empty', 'No results.')
  })

  // Only the PICKER waits on the lookup: Applica also commits "use the run
  // default" (a `null` operator), which the filter never governs, so gating it
  // would take that action away for the length of a request.
  it('keeps the picker disabled while the scope is still resolving (AC-043)', async () => {
    let resolveScope: (value: ReturnType<typeof scope>) => void = () => {}
    fetchAssignmentScopeMock.mockReturnValue(
      new Promise<ReturnType<typeof scope>>((resolve) => {
        resolveScope = resolve
      }),
    )
    renderCell()

    fireEvent.click(screen.getByRole('button', { name: 'Edit operator' }))

    expect(screen.getByRole('button', { name: 'Operator' })).toBeDisabled()
    expect(screen.getByRole('button', { name: 'Apply' })).toBeEnabled()

    resolveScope(scope({ product_category_ids: [4] }))
    await waitFor(() => expect(screen.getByRole('button', { name: 'Operator' })).toBeEnabled())
  })

  // AC-034: a failed resolution must never degrade into an unfiltered list.
  it('keeps the picker disabled and unfiltered when the scope lookup fails', async () => {
    fetchAssignmentScopeMock.mockRejectedValue(new Error('boom'))
    renderCell()

    fireEvent.click(screen.getByRole('button', { name: 'Edit operator' }))

    await waitFor(() => expect(fetchAssignmentScopeMock).toHaveBeenCalledTimes(1))
    await waitFor(() => expect(screen.getByRole('button', { name: 'Operator' })).toBeDisabled())
    expect(screen.getByRole('button', { name: 'Operator' })).toHaveAttribute('data-params', '')
  })

  it('announces an explicit "no enabled operator" empty state once the filter applies (AC-032)', async () => {
    fetchAssignmentScopeMock.mockResolvedValue(
      scope({ product_category_ids: [4], operational_site_id: 84 }),
    )
    renderCell()

    fireEvent.click(screen.getByRole('button', { name: 'Edit operator' }))

    await waitFor(() =>
      expect(screen.getByRole('button', { name: 'Operator' })).toHaveAttribute(
        'data-empty',
        'No operator is enabled for this row (Site and product categories).',
      ),
    )
  })
})
