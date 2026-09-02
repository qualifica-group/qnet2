import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
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

vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    value,
    onChange,
    labels,
  }: {
    value: number | null
    onChange: (value: number | null) => void
    labels: { triggerLabel: string }
  }) => (
    <button type="button" aria-label={labels.triggerLabel} onClick={() => onChange(42)}>
      {value ?? 'none'}
    </button>
  ),
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
})

/** Most tests aren't about the default-hint bug: default a global default id so the hint text is stable. */
const DEFAULT_GLOBAL_OPERATOR_ID = 7

function renderCell(overrides: Partial<ReviewOperatorCellParams> = {}) {
  const node = { setData: vi.fn() } as unknown as IRowNode<ImportRunRowItem>
  const onApplyOperator = vi.fn().mockResolvedValue(undefined)
  const context: ReviewOperatorGridContext = {
    onApplyOperator,
    globalDefaultOperatorId: DEFAULT_GLOBAL_OPERATOR_ID,
  }
  render(
    <ReviewOperatorCell
      {...({
        data: rowItem(),
        node,
        context,
        ...overrides,
      } as ReviewOperatorCellParams & ICellRendererParams)}
    />,
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
      context: { onApplyOperator: vi.fn().mockResolvedValue(undefined), globalDefaultOperatorId: null },
    })
    expect(screen.getByRole('button', { name: 'Edit operator' })).toHaveTextContent('—')
    expect(screen.queryByText('Default operator')).not.toBeInTheDocument()
  })

  it('shows an empty placeholder in readOnly mode too when the run has no global default operator', () => {
    renderCell({
      readOnly: true,
      context: { onApplyOperator: vi.fn().mockResolvedValue(undefined), globalDefaultOperatorId: null },
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
      <ReviewOperatorCell
        {...({
          data: rowItem(),
          node,
          context: { onApplyOperator, globalDefaultOperatorId: DEFAULT_GLOBAL_OPERATOR_ID },
        } as ReviewOperatorCellParams & ICellRendererParams)}
      />,
    )

    fireEvent.click(screen.getByRole('button', { name: 'Edit operator' }))
    fireEvent.click(screen.getByRole('button', { name: 'Apply' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Some values are not valid. Please check and try again.')
    expect(screen.getByRole('dialog')).toBeInTheDocument()
    expect(node.setData).not.toHaveBeenCalled()
  })
})
