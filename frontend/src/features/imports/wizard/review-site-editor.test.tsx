import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import type { ICellRendererParams, IRowNode } from 'ag-grid-community'
import i18n from '@/i18n'
import '@/features/imports/wizard/i18n'
import {
  ReviewSiteCell,
  type ReviewSiteCellParams,
  type ReviewSiteGridContext,
} from '@/features/imports/wizard/review-site-editor'
import type { ImportRunRowItem } from '@/features/imports/wizard/types'

/**
 * Per-row operational-site override cell: shows the row's own site or an em
 * dash (no run default to fall back to), click opens a popup with a site
 * picker precompiled from the row's current override, Applica sends a single
 * PATCH via `context.onApplySite`, "Clear" clears the local selection before
 * Applica, and Annulla/close send nothing.
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
    <button type="button" aria-label={labels.triggerLabel} onClick={() => onChange(99)}>
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

function renderCell(overrides: Partial<ReviewSiteCellParams> = {}) {
  const node = { setData: vi.fn() } as unknown as IRowNode<ImportRunRowItem>
  const onApplySite = vi.fn().mockResolvedValue(undefined)
  const context: ReviewSiteGridContext = {
    onApplySite,
    globalDefaultSiteId: null,
  }
  render(
    <ReviewSiteCell
      {...({
        data: rowItem(),
        node,
        context,
        ...overrides,
      } as ReviewSiteCellParams & ICellRendererParams)}
    />,
  )
  return { node, onApplySite }
}

describe('ReviewSiteCell', () => {
  it('shows an em dash and opens a popup precompiled from the row override when the run has no default site', () => {
    renderCell()
    expect(screen.getByRole('button', { name: 'Edit site' })).toHaveTextContent('—')

    fireEvent.click(screen.getByRole('button', { name: 'Edit site' }))

    expect(screen.getByRole('dialog')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Site' })).toHaveTextContent('none')
  })

  it('shows an em dash placeholder in readOnly mode too', () => {
    renderCell({ readOnly: true })
    expect(screen.getByText('—')).toBeInTheDocument()
    expect(screen.queryByRole('button')).not.toBeInTheDocument()
  })

  it("shows the row's own site name when overridden", () => {
    renderCell({ data: rowItem({ operational_site_id: 5, operational_site: { id: 5, name: 'Milano' } }) })
    expect(screen.getByRole('button', { name: 'Edit site' })).toHaveTextContent('Milano')
  })

  it('sends a single PATCH via context.onApplySite with the picked id, then closes', async () => {
    const { node, onApplySite } = renderCell()

    fireEvent.click(screen.getByRole('button', { name: 'Edit site' }))
    fireEvent.click(screen.getByRole('button', { name: 'Site' }))
    fireEvent.click(screen.getByRole('button', { name: 'Apply' }))

    await waitFor(() => expect(onApplySite).toHaveBeenCalledWith(rowItem(), 99, node))
    expect(onApplySite).toHaveBeenCalledTimes(1)
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
  })

  it('"Clear" clears the local selection so Applica sends `null`', async () => {
    const overriddenRow = rowItem({ operational_site_id: 5, operational_site: { id: 5, name: 'Milano' } })
    const { node, onApplySite } = renderCell({ data: overriddenRow })

    fireEvent.click(screen.getByRole('button', { name: 'Edit site' }))
    fireEvent.click(screen.getByRole('button', { name: 'Clear' }))
    fireEvent.click(screen.getByRole('button', { name: 'Apply' }))

    await waitFor(() => expect(onApplySite).toHaveBeenCalledWith(overriddenRow, null, node))
  })

  it('Annulla closes the popup without calling onApplySite', () => {
    const { onApplySite } = renderCell()

    fireEvent.click(screen.getByRole('button', { name: 'Edit site' }))
    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))

    expect(onApplySite).not.toHaveBeenCalled()
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('renders plain text with no popup affordance in readOnly mode', () => {
    renderCell({ readOnly: true, data: rowItem({ operational_site_id: 5, operational_site: { id: 5, name: 'Milano' } }) })

    expect(screen.getByText('Milano')).toBeInTheDocument()
    expect(screen.queryByRole('button')).not.toBeInTheDocument()
  })

  it('shows an accessible error and keeps the popup open when the PATCH fails', async () => {
    const onApplySite = vi.fn().mockRejectedValue({ isAxiosError: true, response: { status: 422 } })
    const node = { setData: vi.fn() } as unknown as IRowNode<ImportRunRowItem>
    render(
      <ReviewSiteCell
        {...({
          data: rowItem(),
          node,
          context: { onApplySite, globalDefaultSiteId: null },
        } as ReviewSiteCellParams & ICellRendererParams)}
      />,
    )

    fireEvent.click(screen.getByRole('button', { name: 'Edit site' }))
    fireEvent.click(screen.getByRole('button', { name: 'Apply' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Some values are not valid. Please check and try again.')
    expect(screen.getByRole('dialog')).toBeInTheDocument()
    expect(node.setData).not.toHaveBeenCalled()
  })
})
