import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { RequestManagementTable } from '@/features/request-management/request-management-table'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * The `activity` row action of the request-management adapter: clicking it
 * opens the shared `ResourceActivityDialog` on THIS module's own activity
 * resource key — `request-management`, not `opportunities`, since the read
 * gate is this module's permission set — keyed on the row's `opportunity_id`,
 * never its `id`: the row is now an Offerta (spec 0086 D-1), but activity
 * history stays anchored to the Opportunity (D-9). The generic `<TableView>`
 * and `ActivityLogSection` are stubbed (their own behavior has its own
 * suites); this is only about what the adapter wires.
 */

vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'page',
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

const activityLogSectionMock = vi.fn()
vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: (props: { resource: string; id: number }) => {
    activityLogSectionMock(props)
    return <div>{`activity-log:${props.resource}:${props.id}`}</div>
  },
}))

// `opportunity_id` deliberately DIFFERENT from `id` (the row's Offerta id):
// proves the dialog is keyed on the Opportunity, not the row's own record.
const ROW: TableRow = { id: 7, opportunity_id: 99, actions: ['view', 'activity'], editable: false }

const ACTIVITY_ACTION: TableActionDefinition = {
  key: 'activity',
  label: 'actions.activity',
  icon: 'history',
  type: 'action',
  confirm: false,
}

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<{ refresh: () => void }, { domain: string; onAction: RowActionHandler }>(
    function TableViewStub({ domain, onAction }, ref) {
      useImperativeHandle(ref, () => ({ refresh: vi.fn() }))
      return (
        <div role="region" aria-label={`table-${domain}`}>
          <button type="button" onClick={() => onAction(ACTIVITY_ACTION, ROW)}>
            trigger-activity
          </button>
        </div>
      )
    },
  ),
}))

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <RequestManagementTable />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  activityLogSectionMock.mockReset()
})

describe('RequestManagementTable — "activity" row action', () => {
  it('opens the activity dialog on the request-management resource key', () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'trigger-activity' }))

    expect(screen.getByRole('dialog')).toBeInTheDocument()
    expect(screen.getByText('activity-log:request-management:99')).toBeInTheDocument()
    expect(activityLogSectionMock).toHaveBeenCalledWith(
      expect.objectContaining({ resource: 'request-management', id: 99 }),
    )
  })

  it('closes without leaving the timeline mounted', () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'trigger-activity' }))
    fireEvent.keyDown(screen.getByRole('dialog'), { key: 'Escape' })

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(screen.queryByText('activity-log:request-management:99')).not.toBeInTheDocument()
  })
})
