import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { NotificationsTable } from '@/features/notifications/notifications-table'
import type { BulkAction, TableSelection } from '@/features/table/use-bulk-actions-slot'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * Adapter suite for the `notifications` domain (spec 0150). `<TableView>`
 * (AG Grid + SSRM) is a framework piece outside this microtask's ownership,
 * stubbed the same way `leads-table-assign.test.tsx` stubs it: this suite is
 * about what `NotificationsTable` does with `onAction`/`getBulkActions` and
 * the header button, against the REAL `useNotificationActions` mutations
 * (mocked here to synchronously invoke the caller's own `onSuccess`, so the
 * "refresh grid after every mutation" contract (D-7) is observable).
 */

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

function mutateWithOnSuccess() {
  return vi.fn((_variables: unknown, options?: { onSuccess?: () => void }) => {
    options?.onSuccess?.()
  })
}

const markAsReadMutateMock = mutateWithOnSuccess()
const markAsUnreadMutateMock = mutateWithOnSuccess()
const markAllAsReadMutateMock = mutateWithOnSuccess()
const markManyAsReadMutateMock = mutateWithOnSuccess()

vi.mock('@/features/notifications/use-notification-actions', () => ({
  useNotificationActions: () => ({
    markAsRead: { mutate: markAsReadMutateMock },
    markAsUnread: { mutate: markAsUnreadMutateMock },
    markAllAsRead: { mutate: markAllAsReadMutateMock, isPending: false },
    markManyAsRead: { mutate: markManyAsReadMutateMock, isPending: false },
  }),
}))

const refreshMock = vi.fn()
const clearSelectionMock = vi.fn()
let capturedOnAction: RowActionHandler | undefined
let capturedGetBulkActions: ((selection: TableSelection) => BulkAction[]) | undefined

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<
    { refresh: () => void; clearSelection: () => void },
    {
      domain: string
      onAction?: RowActionHandler
      getBulkActions?: (selection: TableSelection) => BulkAction[]
    }
  >(function TableViewStub({ domain, onAction, getBulkActions }, ref) {
    useImperativeHandle(ref, () => ({ refresh: refreshMock, clearSelection: clearSelectionMock }))
    capturedOnAction = onAction
    capturedGetBulkActions = getBulkActions
    return <div role="region" aria-label={`table-${domain}`} />
  }),
}))

const MARK_READ_ACTION: TableActionDefinition = {
  key: 'mark-read',
  label: 'notifications.actions.markRead',
  icon: 'mail-open',
  type: 'link',
  confirm: false,
}
const MARK_UNREAD_ACTION: TableActionDefinition = {
  key: 'mark-unread',
  label: 'notifications.actions.markUnread',
  icon: 'mail',
  type: 'link',
  confirm: false,
}

function notificationRow(overrides: Partial<TableRow> = {}): TableRow {
  return { id: 'n1', actions: ['mark-read'], status: 'unread', ...overrides }
}

function renderTable() {
  return render(
    <MemoryRouter>
      <NotificationsTable />
    </MemoryRouter>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  markAsReadMutateMock.mockClear()
  markAsUnreadMutateMock.mockClear()
  markAllAsReadMutateMock.mockClear()
  markManyAsReadMutateMock.mockClear()
  refreshMock.mockClear()
  clearSelectionMock.mockClear()
})

describe('NotificationsTable — domain wiring', () => {
  it('mounts <TableView domain="notifications">', () => {
    renderTable()

    expect(screen.getByRole('region', { name: 'table-notifications' })).toBeInTheDocument()
  })
})

describe('NotificationsTable — row actions (AC-010)', () => {
  it('mark-read calls the PATCH .../read mutation, then refreshes the grid', () => {
    renderTable()

    capturedOnAction?.(MARK_READ_ACTION, notificationRow({ id: 'n1' }))

    expect(markAsReadMutateMock).toHaveBeenCalledWith('n1', expect.objectContaining({ onSuccess: expect.any(Function) }))
    expect(refreshMock).toHaveBeenCalledTimes(1)
  })

  it('mark-unread calls the PATCH .../unread mutation, then refreshes the grid', () => {
    renderTable()

    capturedOnAction?.(MARK_UNREAD_ACTION, notificationRow({ id: 'n2', status: 'read' }))

    expect(markAsUnreadMutateMock).toHaveBeenCalledWith('n2', expect.objectContaining({ onSuccess: expect.any(Function) }))
    expect(refreshMock).toHaveBeenCalledTimes(1)
  })
})

describe('NotificationsTable — bulk and header actions (AC-011)', () => {
  it('offers only "mark selected as read" from the bulk slot, never a delete entry', () => {
    renderTable()

    const selection: TableSelection = { ids: ['n1', 'n2'], rows: [notificationRow({ id: 'n1' }), notificationRow({ id: 'n2' })] }
    const actions = capturedGetBulkActions?.(selection) ?? []

    expect(actions.map((action) => action.key)).toEqual(['mark-selected-read'])
  })

  it('bulk "mark selected as read" posts the string ids, clears the selection and refreshes', () => {
    renderTable()

    const selection: TableSelection = { ids: ['n1', 'n2'], rows: [] }
    const [action] = capturedGetBulkActions?.(selection) ?? []
    action?.onSelect()

    expect(markManyAsReadMutateMock).toHaveBeenCalledWith(['n1', 'n2'], expect.objectContaining({ onSuccess: expect.any(Function) }))
    expect(clearSelectionMock).toHaveBeenCalledTimes(1)
    expect(refreshMock).toHaveBeenCalledTimes(1)
  })

  it('the header "Mark all as read" button calls POST /notifications/read-all, then refreshes', () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'Mark all as read' }))

    expect(markAllAsReadMutateMock).toHaveBeenCalledWith(undefined, expect.objectContaining({ onSuccess: expect.any(Function) }))
    expect(refreshMock).toHaveBeenCalledTimes(1)
  })
})
