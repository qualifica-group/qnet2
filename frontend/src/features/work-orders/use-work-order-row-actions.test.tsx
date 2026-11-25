import { beforeEach, describe, expect, it, vi } from 'vitest'
import { act, render, renderHook, screen } from '@testing-library/react'
import { useWorkOrderRowActions } from '@/features/work-orders/use-work-order-row-actions'
import type { TableActionDefinition } from '@/features/table/types'
import type { ICellRendererParams } from 'ag-grid-community'

/**
 * Spec 0405: the legacy `OrdersTable` actions. The eye opens the record,
 * "Responsabili e Partecipanti" its team dialog, Info and the menu entries
 * open the record on that tab. REQUIREMENT CHANGED: replaces the closure/proforma/
 * documents/notes row actions this hook used to run. Elimina came back on the row
 * (user request 2026-11-19).
 */

const openView = vi.fn()
const openViewTab = vi.fn()
/** The module domain whose opener ran: a row opens in the module of its type (2026-11-25). */
const openedIn = vi.fn()
vi.mock('@/features/modules/use-module-opener', () => ({
  useModuleOpener: (domain: string) => ({
    openView: (row: unknown) => {
      openedIn(domain)
      openView(row)
    },
    openViewTab: (row: unknown, tab: string) => {
      openedIn(domain)
      openViewTab(row, tab)
    },
    openCreateWith: vi.fn(),
    sheet: null,
  }),
}))

const remove = vi.fn()
const useDeleteWorkOrder = vi.fn((onDeleted: () => void) => {
  void onDeleted
  return { remove, isPending: false }
})
vi.mock('@/features/work-orders/use-delete-work-order', () => ({
  useDeleteWorkOrder: (onDeleted: () => void) => useDeleteWorkOrder(onDeleted),
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
const PROJECT_ROW = { ...ROW, id: 9, type: 'project' }

function action(key: string): TableActionDefinition {
  return { key, label: key, icon: 'info', type: 'link', confirm: false }
}

beforeEach(() => {
  openedIn.mockReset()
  openView.mockReset()
  openViewTab.mockReset()
  remove.mockReset()
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

  it('Elimina deletes the row (already confirmed by the row menu) and a delete refreshes the grid', () => {
    const onMutated = vi.fn()
    const { result } = renderHook(() => useWorkOrderRowActions({ onMutated }))

    act(() => result.current.handleAction(action('delete'), ROW))

    expect(remove).toHaveBeenCalledWith(7)
    expect(useDeleteWorkOrder).toHaveBeenLastCalledWith(onMutated)
    expect(openView).not.toHaveBeenCalled()
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

  it('opens a project row in "Commesse di Progetto" and any other in "Commesse"', () => {
    const { result } = renderHook(() => useWorkOrderRowActions({ onMutated: vi.fn() }))

    act(() => result.current.handleAction(action('view'), { ...ROW, type: 'processing' }))
    act(() => result.current.handleAction(action('notes'), PROJECT_ROW))
    act(() => result.current.handleAction(action('view'), PROJECT_ROW))

    expect(openedIn.mock.calls.map(([domain]) => domain)).toEqual(['work-orders', 'project-work-orders', 'project-work-orders'])
    expect(openViewTab).toHaveBeenCalledWith(PROJECT_ROW, 'notes')
  })

  it('links a project row to its "Commesse di Progetto" page', () => {
    const { result } = renderHook(() => useWorkOrderRowActions({ onMutated: vi.fn() }))
    const { resolveActionHref } = result.current

    expect(resolveActionHref(action('view'), PROJECT_ROW)).toBe('/project-work-orders/9')
    expect(resolveActionHref(action('tasks'), PROJECT_ROW)).toBe('/project-work-orders/9?tab=tasks')
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
