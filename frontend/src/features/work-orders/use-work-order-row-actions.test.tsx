import { beforeEach, describe, expect, it, vi } from 'vitest'
import { act, render, renderHook, screen } from '@testing-library/react'
import { useWorkOrderRowActions } from '@/features/work-orders/use-work-order-row-actions'
import type { TableActionDefinition } from '@/features/table/types'
import type { ICellRendererParams } from 'ag-grid-community'

/**
 * Spec 0405: the legacy `OrdersTable` actions. The eye opens the record,
 * "Responsabili e Partecipanti" its team dialog, Info and the menu entries
 * open the record on that tab. REQUIREMENT CHANGED: replaces the closure/proforma/
 * documents/notes row actions this hook used to run.
 */

const openView = vi.fn()
const openViewTab = vi.fn()
vi.mock('@/features/modules/use-module-opener', () => ({
  useModuleOpener: () => ({ openView, openViewTab, openCreateWith: vi.fn(), sheet: null }),
}))

vi.mock('@/features/work-orders/work-order-team-dialog', () => ({
  WorkOrderTeamDialog: ({ workOrderId }: { workOrderId: number | null }) =>
    workOrderId === null ? null : <div role="dialog" aria-label={`team-${workOrderId}`} />,
}))

vi.mock('@/features/work-orders/work-order-progress-dialog', () => ({ WorkOrderProgressDialog: () => null }))

vi.mock('@/features/work-orders/proforma-request-dialog', () => ({
  ProformaRequestDialog: ({ workOrderId, workOrderCode, onSent }: { workOrderId: number; workOrderCode: string; onSent: () => void }) => (
    <div role="dialog" aria-label={`proforma-${workOrderId}-${workOrderCode}`}>
      <button type="button" onClick={onSent}>
        send
      </button>
    </div>
  ),
}))

const ROW = { id: 7, actions: ['view', 'team', 'info', 'notes', 'tasks', 'documents'] }

function action(key: string): TableActionDefinition {
  return { key, label: key, icon: 'info', type: 'link', confirm: false }
}

beforeEach(() => {
  openView.mockReset()
  openViewTab.mockReset()
})

describe('useWorkOrderRowActions (spec 0405)', () => {
  it('the eye opens the record', () => {
    const { result } = renderHook(() => useWorkOrderRowActions({ onMutated: vi.fn() }))

    act(() => result.current.handleAction(action('view'), ROW))

    expect(openView).toHaveBeenCalledWith(ROW)
    expect(openViewTab).not.toHaveBeenCalled()
  })

  it.each(['info', 'notes', 'tasks', 'documents'])('the "%s" entry opens the record on that tab', (key) => {
    const { result } = renderHook(() => useWorkOrderRowActions({ onMutated: vi.fn() }))

    act(() => result.current.handleAction(action(key), ROW))

    expect(openViewTab).toHaveBeenCalledWith(ROW, key)
  })

  it('"Responsabili e Partecipanti" opens the team dialog of the row', () => {
    const { result } = renderHook(() => useWorkOrderRowActions({ onMutated: vi.fn() }))

    act(() => result.current.handleAction(action('team'), ROW))
    render(<>{result.current.dialogs}</>)

    expect(screen.getByRole('dialog', { name: 'team-7' })).toBeInTheDocument()
    expect(openView).not.toHaveBeenCalled()
  })

  it('links the eye to the record page and the menu entries to the page on their tab', () => {
    const { result } = renderHook(() => useWorkOrderRowActions({ onMutated: vi.fn() }))
    const { resolveActionHref } = result.current

    expect(resolveActionHref(action('view'), ROW)).toBe('/work-orders/7')
    expect(resolveActionHref(action('notes'), ROW)).toBe('/work-orders/7?tab=notes')
    expect(resolveActionHref(action('team'), ROW)).toBeUndefined()
  })

  it('the "€" cell opens the proforma request dialog of its row and a sent request refreshes the grid (spec 0407)', () => {
    const onMutated = vi.fn()
    const { result, rerender } = renderHook(() => useWorkOrderRowActions({ onMutated }))
    const cell = result.current.renderers.proforma_status
    const params = { value: 'none', data: { id: 7, code: 'C-007' } } as unknown as ICellRendererParams
    render(<>{cell?.(params)}</>)

    act(() => screen.getByRole('button', { name: 'Richiedi emissione proforma' }).click())
    rerender()
    const { unmount } = render(<>{result.current.dialogs}</>)

    expect(screen.getByRole('dialog', { name: 'proforma-7-C-007' })).toBeInTheDocument()
    act(() => screen.getByRole('button', { name: 'send' }).click())
    expect(onMutated).toHaveBeenCalledOnce()
    unmount()
  })
})
