import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ComponentProps } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
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

const OPERATOR_PICK_ID = 42
const PRODUCT_PICK_ID = 9

// The stub also surfaces the props the Sede/competence filter drives (spec
// 0110, 0113): the forwarded `params` and the picker's `disabled`/`empty`
// state. Only the Operatore picker is ever rendered here — the Sede select is
// opt-in and this surface never opts in (AC-026).
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
      onClick={() => onChange(OPERATOR_PICK_ID)}
    >
      {value ?? 'none'}
    </button>
  ),
}))

// `useAssignmentScope` (spec 0110 AC-041, 0113) resolves the selection's Sede,
// competence requirement and campaigns through this endpoint; every test
// drives it explicitly.
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
  return { product_category_ids: [], operational_site_id: null, campaign_ids: [1], ...overrides }
}

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
  fetchAssignmentScopeMock.mockResolvedValue(scope())
})

/** The run the staged rows belong to; scopes the competence lookup (spec 0110). */
const IMPORT_RUN_ID = 55

function renderBar(overrides: Partial<ComponentProps<typeof ReviewBulkAssignBar>> = {}) {
  const onAssign = overrides.onAssign ?? vi.fn().mockResolvedValue(undefined)
  const onAssignProducts = overrides.onAssignProducts ?? vi.fn().mockResolvedValue(undefined)
  // One client per test (never per render), so the cache never leaks across cases.
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <ReviewBulkAssignBar
        selection={{ selectAll: false, toggledNodes: ['1', '2', '3'] }}
        importRunId={IMPORT_RUN_ID}
        totalRows={10}
        campaignCategoryIds={[]}
        onAssign={onAssign}
        onAssignProducts={onAssignProducts}
        {...overrides}
      />
    </QueryClientProvider>,
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

  // AC-026: the Sede is derived from each row server-side, so the popup has
  // no Sede field at all and `balanced` confirms on the mode alone.
  it('renders no Sede field and confirms "balanced" without any further pick', async () => {
    const { onAssign } = renderBar({ selection: { selectAll: false, toggledNodes: ['1'] } })
    openAssignOperatorsDialog()

    expect(screen.queryByRole('button', { name: 'Site' })).not.toBeInTheDocument()

    fireEvent.click(screen.getByRole('radio', { name: 'Balanced split' }))
    fireEvent.click(screen.getByRole('button', { name: 'Assign' }))

    await waitFor(() => expect(onAssign).toHaveBeenCalledWith({ mode: 'balanced' }))
    await waitFor(() => expect(screen.queryByText('1 lead(s) selected.')).not.toBeInTheDocument())
  })

  it('calls onAssign with mode "single" and the picked operator, no Sede', async () => {
    const { onAssign } = renderBar({ selection: { selectAll: false, toggledNodes: ['1'] } })
    openAssignOperatorsDialog()
    fireEvent.click(screen.getByRole('radio', { name: 'Assign to operator' }))
    // The picker only opens up once the selection's scope is resolved (spec
    // 0110 AC-043, 0113 AC-034); before that it is deliberately disabled.
    await waitFor(() => expect(screen.getByRole('button', { name: 'Operator' })).toBeEnabled())
    fireEvent.click(screen.getByRole('button', { name: 'Operator' }))
    fireEvent.click(screen.getByRole('button', { name: 'Assign' }))

    await waitFor(() =>
      expect(onAssign).toHaveBeenCalledWith({ mode: 'single', operator_id: OPERATOR_PICK_ID }),
    )
  })

  it('keeps the popup open when onAssign rejects (already surfaced by the caller)', async () => {
    const onAssign = vi.fn().mockRejectedValue(new Error('failed'))
    renderBar({ selection: { selectAll: false, toggledNodes: ['1'] }, onAssign })
    openAssignOperatorsDialog()
    fireEvent.click(screen.getByRole('radio', { name: 'Balanced split' }))
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

/**
 * Spec 0110 AC-041/AC-043 and spec 0113 AC-028/AC-034: the shared popup's
 * Operatore picker only proposes the users of the selection's Sede competent
 * for it, resolved from the SAME `select_all`/`row_ids` state the bulk
 * assignment itself targets.
 */
describe('ReviewBulkAssignBar — Sede + competence filter (spec 0110, 0113)', () => {
  it('resolves the scope only once the operators popup is open', async () => {
    renderBar({ selection: { selectAll: false, toggledNodes: ['1', '2'] } })

    expect(fetchAssignmentScopeMock).not.toHaveBeenCalled()

    openAssignOperatorsDialog()

    await waitFor(() =>
      expect(fetchAssignmentScopeMock).toHaveBeenCalledWith({
        domain: 'import_rows',
        import_run_id: IMPORT_RUN_ID,
        select_all: false,
        row_ids: [1, 2],
      }),
    )
  })

  it('mirrors a select-all selection, whose row ids are the EXCLUDED ones', async () => {
    renderBar({ selection: { selectAll: true, toggledNodes: ['9'] } })
    openAssignOperatorsDialog()

    await waitFor(() =>
      expect(fetchAssignmentScopeMock).toHaveBeenCalledWith({
        domain: 'import_rows',
        import_run_id: IMPORT_RUN_ID,
        select_all: true,
        row_ids: [9],
      }),
    )
  })

  // AC-028: both filters travel to `users/for-select`, none of them optional.
  it('forwards the resolved Sede and categories to the picker', async () => {
    fetchAssignmentScopeMock.mockResolvedValue(
      scope({ product_category_ids: [4, 9], operational_site_id: 84 }),
    )
    renderBar({ selection: { selectAll: false, toggledNodes: ['1'] } })
    openAssignOperatorsDialog()
    fireEvent.click(screen.getByRole('radio', { name: 'Assign to operator' }))

    await waitFor(() =>
      expect(screen.getByRole('button', { name: 'Operator' })).toHaveAttribute(
        'data-params',
        JSON.stringify({ operational_site_id: 84, competence_category_ids: [4, 9] }),
      ),
    )
    expect(screen.getByRole('button', { name: 'Operator' })).toHaveAttribute(
      'data-empty',
      'No operator is competent for the selected records.',
    )
  })

  it('applies no filter when the selection expresses neither Sede nor requirement', async () => {
    renderBar({ selection: { selectAll: false, toggledNodes: ['1'] } })
    openAssignOperatorsDialog()
    fireEvent.click(screen.getByRole('radio', { name: 'Assign to operator' }))

    await waitFor(() => expect(fetchAssignmentScopeMock).toHaveBeenCalledTimes(1))
    expect(screen.getByRole('button', { name: 'Operator' })).toHaveAttribute('data-params', '')
    expect(screen.getByRole('button', { name: 'Operator' })).toHaveAttribute('data-empty', 'No results found.')
  })

  it('keeps the picker disabled, with the confirm action, while the scope resolves (AC-043)', async () => {
    let resolveScope: (value: ReturnType<typeof scope>) => void = () => {}
    fetchAssignmentScopeMock.mockReturnValue(
      new Promise<ReturnType<typeof scope>>((resolve) => {
        resolveScope = resolve
      }),
    )
    renderBar({ selection: { selectAll: false, toggledNodes: ['1'] } })
    openAssignOperatorsDialog()
    fireEvent.click(screen.getByRole('radio', { name: 'Assign to operator' }))

    expect(screen.getByRole('button', { name: 'Operator' })).toBeDisabled()
    // No operator can be picked yet, so the confirm action stays unreachable.
    expect(screen.getByRole('button', { name: 'Assign' })).toBeDisabled()

    resolveScope(scope({ product_category_ids: [4] }))
    await waitFor(() => expect(screen.getByRole('button', { name: 'Operator' })).toBeEnabled())
  })

  // AC-034: a failed resolution must never fall back to an unfiltered list.
  it('keeps the picker disabled and unfiltered when the scope lookup fails', async () => {
    fetchAssignmentScopeMock.mockRejectedValue(new Error('boom'))
    renderBar({ selection: { selectAll: false, toggledNodes: ['1'] } })
    openAssignOperatorsDialog()
    fireEvent.click(screen.getByRole('radio', { name: 'Assign to operator' }))

    await waitFor(() => expect(fetchAssignmentScopeMock).toHaveBeenCalledTimes(1))
    await waitFor(() => expect(screen.getByRole('button', { name: 'Operator' })).toBeDisabled())
    expect(screen.getByRole('button', { name: 'Operator' })).toHaveAttribute('data-params', '')
  })
})

/**
 * Spec 0113 D-5/AC-029/AC-030: "Assegna a operatore" cannot express a
 * selection spanning several campaigns (no single operator is on every
 * campaign's Sede), while "Smistamento equo" works row by row and stays
 * available. The rule lives only here, in the import wizard.
 */
describe('ReviewBulkAssignBar — mixed campaigns (spec 0113)', () => {
  it('disables the "Assign to operator" card with a readable reason, keeping "Balanced split" usable', async () => {
    fetchAssignmentScopeMock.mockResolvedValue(scope({ campaign_ids: [1, 2] }))
    const { onAssign } = renderBar({ selection: { selectAll: false, toggledNodes: ['1', '2'] } })
    openAssignOperatorsDialog()

    const single = screen.getByRole('radio', { name: 'Assign to operator' })
    await waitFor(() => expect(single).toHaveAttribute('aria-disabled', 'true'))
    expect(
      screen.getByText('Unavailable: the selection spans rows from different campaigns.'),
    ).toBeInTheDocument()

    // Clicking it selects nothing: no Operatore field appears.
    fireEvent.click(single)
    expect(screen.queryByRole('button', { name: 'Operator' })).not.toBeInTheDocument()

    const balanced = screen.getByRole('radio', { name: 'Balanced split' })
    expect(balanced).not.toHaveAttribute('aria-disabled')
    fireEvent.click(balanced)
    fireEvent.click(screen.getByRole('button', { name: 'Assign' }))

    await waitFor(() => expect(onAssign).toHaveBeenCalledWith({ mode: 'balanced' }))
  })

  // An unresolved (or failed) scope is not "mixed campaigns": announcing that
  // reason while the state is unknown would be a false message. The picker is
  // already inhibited by the unresolved Sede, which is the honest signal.
  it('disables no card while the scope is still resolving', () => {
    fetchAssignmentScopeMock.mockReturnValue(new Promise<ReturnType<typeof scope>>(() => {}))
    renderBar({ selection: { selectAll: false, toggledNodes: ['1', '2'] } })
    openAssignOperatorsDialog()

    expect(screen.getByRole('radio', { name: 'Assign to operator' })).not.toHaveAttribute('aria-disabled')
    expect(screen.getByRole('radio', { name: 'Balanced split' })).not.toHaveAttribute('aria-disabled')
    expect(
      screen.queryByText('Unavailable: the selection spans rows from different campaigns.'),
    ).not.toBeInTheDocument()
  })

  it('disables no card when the scope lookup fails, inhibiting the picker instead', async () => {
    fetchAssignmentScopeMock.mockRejectedValue(new Error('boom'))
    renderBar({ selection: { selectAll: false, toggledNodes: ['1', '2'] } })
    openAssignOperatorsDialog()

    await waitFor(() => expect(fetchAssignmentScopeMock).toHaveBeenCalledTimes(1))
    const single = screen.getByRole('radio', { name: 'Assign to operator' })
    expect(single).not.toHaveAttribute('aria-disabled')
    expect(
      screen.queryByText('Unavailable: the selection spans rows from different campaigns.'),
    ).not.toBeInTheDocument()

    // Still selectable, and what it reveals is a picker that cannot be used.
    fireEvent.click(single)
    await waitFor(() => expect(screen.getByRole('button', { name: 'Operator' })).toBeDisabled())
  })

  it('leaves both cards enabled for a selection spanning a single campaign (AC-030)', async () => {
    fetchAssignmentScopeMock.mockResolvedValue(scope({ campaign_ids: [1] }))
    renderBar({ selection: { selectAll: false, toggledNodes: ['1', '2'] } })
    openAssignOperatorsDialog()

    await waitFor(() => expect(fetchAssignmentScopeMock).toHaveBeenCalledTimes(1))
    expect(screen.getByRole('radio', { name: 'Assign to operator' })).not.toHaveAttribute('aria-disabled')
    expect(screen.getByRole('radio', { name: 'Balanced split' })).not.toHaveAttribute('aria-disabled')
    expect(
      screen.queryByText('Unavailable: the selection spans rows from different campaigns.'),
    ).not.toBeInTheDocument()
  })
})
