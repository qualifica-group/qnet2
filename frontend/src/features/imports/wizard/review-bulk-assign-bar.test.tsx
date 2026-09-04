import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ComponentProps } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import '@/features/imports/wizard/i18n'
import { ReviewBulkAssignBar } from '@/features/imports/wizard/review-bulk-assign-bar'

/**
 * Compact toolbar shown above the review grid while the SSRM selection is
 * non-empty: the selection count (or "All") plus a single "Azioni" dropdown
 * (client directive 2026-07-21: never a row of loose buttons) with two
 * entries — "Assegna operatori", opening the SHARED popup the Lead table
 * uses (spec 0048 AC-050, with only its `AsyncPaginatedSelect` pickers
 * stubbed, mirrors `assign-operators-dialog.test.tsx`) — and "Assegna
 * prodotti" (spec 0094 bulk delta), opening the dedicated bulk products
 * popup (with `AsyncPaginatedMultiSelect` stubbed, mirrors
 * `review-products-editor.test.tsx`).
 */

const SITE_PICK_ID = 7
const OPERATOR_PICK_ID = 42
const PRODUCT_PICK_ID = 9

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

vi.mock('@/components/ui/async-paginated-multi-select', () => ({
  AsyncPaginatedMultiSelect: ({
    value,
    onChange,
    labels,
  }: {
    value: number[]
    onChange: (value: number[]) => void
    labels: { triggerLabel: string }
  }) => (
    <button
      type="button"
      aria-label={labels.triggerLabel}
      onClick={() =>
        onChange(value.includes(PRODUCT_PICK_ID) ? value.filter((id) => id !== PRODUCT_PICK_ID) : [...value, PRODUCT_PICK_ID])
      }
    >
      {value.length > 0 ? value.join(',') : 'none'}
    </button>
  ),
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.clearAllMocks()
})

function renderBar(overrides: Partial<ComponentProps<typeof ReviewBulkAssignBar>> = {}) {
  const onAssign = overrides.onAssign ?? vi.fn().mockResolvedValue(undefined)
  const onAssignProducts = overrides.onAssignProducts ?? vi.fn().mockResolvedValue(undefined)
  render(
    <ReviewBulkAssignBar
      selection={{ selectAll: false, toggledNodes: ['1', '2', '3'] }}
      totalRows={10}
      campaignCategoryIds={[]}
      onAssign={onAssign}
      onAssignProducts={onAssignProducts}
      {...overrides}
    />,
  )
  return { onAssign, onAssignProducts }
}

/** Radix' DropdownMenu trigger opens on `pointerdown`, not `click`. */
function openActionsMenu() {
  fireEvent.pointerDown(screen.getByRole('button', { name: /^Actions/ }), { button: 0, ctrlKey: false })
}

function openAssignOperatorsDialog() {
  openActionsMenu()
  fireEvent.click(screen.getByRole('menuitem', { name: 'Assign operators' }))
}

function openAssignProductsDialog() {
  openActionsMenu()
  fireEvent.click(screen.getByRole('menuitem', { name: 'Assign products' }))
}

describe('ReviewBulkAssignBar — selection label', () => {
  it('shows the selected-count label for a partial selection', () => {
    renderBar({ selection: { selectAll: false, toggledNodes: ['1', '2', '3'] } })
    expect(screen.getByText('3 row(s) selected')).toBeInTheDocument()
  })

  it('shows the "all selected" label for a select-all selection with nothing excluded', () => {
    renderBar({ selection: { selectAll: true, toggledNodes: [] } })
    expect(screen.getByText('All rows selected')).toBeInTheDocument()
  })

  it('shows the excluded count for a select-all selection with some rows excluded', () => {
    renderBar({ selection: { selectAll: true, toggledNodes: ['9'] } })
    expect(screen.getByText('All rows selected (1 excluded)')).toBeInTheDocument()
  })

  it('exposes an accessible toolbar landmark', () => {
    renderBar({ selection: { selectAll: false, toggledNodes: ['1'] } })
    expect(
      screen.getByRole('toolbar', { name: 'Bulk-assign operator, site, or products to the selected rows' }),
    ).toBeInTheDocument()
  })
})

describe('ReviewBulkAssignBar — Azioni dropdown', () => {
  it('renders a single "Azioni" trigger, never a row of loose buttons', () => {
    renderBar()
    expect(screen.getByRole('button', { name: /^Actions/ })).toBeInTheDocument()
    expect(screen.queryByRole('menuitem')).not.toBeInTheDocument()
  })

  it('lists both "Assign operators" and "Assign products" entries', () => {
    renderBar()
    openActionsMenu()
    expect(screen.getByRole('menuitem', { name: 'Assign operators' })).toBeInTheDocument()
    expect(screen.getByRole('menuitem', { name: 'Assign products' })).toBeInTheDocument()
  })
})

describe('ReviewBulkAssignBar — "Assign operators" entry (unchanged behavior)', () => {
  it('opens the shared "Assegna operatori" popup', () => {
    renderBar({ selection: { selectAll: false, toggledNodes: ['1', '2'] } })

    expect(screen.queryByText('2 lead(s) selected.')).not.toBeInTheDocument()
    openAssignOperatorsDialog()

    expect(screen.getByText('2 lead(s) selected.')).toBeInTheDocument()
    expect(screen.getByRole('radio', { name: 'Balanced split' })).toBeInTheDocument()
    expect(screen.getByRole('radio', { name: 'Assign to operator' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Assign' })).toBeInTheDocument()
  })

  it('calls onAssign with mode "balanced" (site only) and closes on success', async () => {
    const { onAssign } = renderBar({ selection: { selectAll: false, toggledNodes: ['1'] } })
    openAssignOperatorsDialog()
    fireEvent.click(screen.getByRole('radio', { name: 'Balanced split' }))
    fireEvent.click(screen.getByRole('button', { name: 'Site' }))
    fireEvent.click(screen.getByRole('button', { name: 'Assign' }))

    await waitFor(() =>
      expect(onAssign).toHaveBeenCalledWith({ operational_site_id: SITE_PICK_ID, mode: 'balanced' }),
    )
    await waitFor(() => expect(screen.queryByText('1 lead(s) selected.')).not.toBeInTheDocument())
  })

  it('calls onAssign with mode "single" (site + operator)', async () => {
    const { onAssign } = renderBar({ selection: { selectAll: false, toggledNodes: ['1'] } })
    openAssignOperatorsDialog()
    fireEvent.click(screen.getByRole('radio', { name: 'Assign to operator' }))
    fireEvent.click(screen.getByRole('button', { name: 'Site' }))
    fireEvent.click(screen.getByRole('button', { name: 'Operator' }))
    fireEvent.click(screen.getByRole('button', { name: 'Assign' }))

    await waitFor(() =>
      expect(onAssign).toHaveBeenCalledWith({
        operational_site_id: SITE_PICK_ID,
        mode: 'single',
        operator_id: OPERATOR_PICK_ID,
      }),
    )
  })

  it('keeps the popup open when onAssign rejects (already surfaced by the caller)', async () => {
    const onAssign = vi.fn().mockRejectedValue(new Error('failed'))
    renderBar({ selection: { selectAll: false, toggledNodes: ['1'] }, onAssign })
    openAssignOperatorsDialog()
    fireEvent.click(screen.getByRole('radio', { name: 'Balanced split' }))
    fireEvent.click(screen.getByRole('button', { name: 'Site' }))
    fireEvent.click(screen.getByRole('button', { name: 'Assign' }))

    await waitFor(() => expect(onAssign).toHaveBeenCalledTimes(1))
    expect(screen.getByText('1 lead(s) selected.')).toBeInTheDocument()
  })
})

describe('ReviewBulkAssignBar — "Assign products" entry (spec 0094 bulk delta)', () => {
  it('opens the bulk products popup with Applica disabled until a product is picked', () => {
    renderBar({ selection: { selectAll: false, toggledNodes: ['1', '2'] } })
    openAssignProductsDialog()

    expect(screen.getByText('Assign products of interest to 2 selected row(s).')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Apply' })).toBeDisabled()
  })

  it('calls onAssignProducts with the picked ids and closes on success', async () => {
    const { onAssignProducts } = renderBar({ selection: { selectAll: false, toggledNodes: ['1', '2'] } })
    openAssignProductsDialog()
    fireEvent.click(screen.getByRole('button', { name: 'Assign products' }))
    fireEvent.click(screen.getByRole('button', { name: 'Apply' }))

    await waitFor(() => expect(onAssignProducts).toHaveBeenCalledWith([PRODUCT_PICK_ID]))
    await waitFor(() =>
      expect(screen.queryByText('Assign products of interest to 2 selected row(s).')).not.toBeInTheDocument(),
    )
  })
})
