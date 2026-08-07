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
 * The `documents` row action of the request-management adapter: clicking it
 * opens the shared `DocumentsDialog` on the POLYMORPHIC OWNER of the row's
 * OWN `opportunity_id`, never its `id` — the row is now an Offerta (spec
 * 0086 D-1), but documents stay anchored to the Opportunity (D-9) — and
 * closing it refreshes the grid so the `documents_count` badge stays
 * current. The generic `<TableView>` and `DocumentsSection` are stubbed
 * (their own behavior is covered by their suites); this one is only about what
 * the adapter does with the action and the dialog's open state.
 */

vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'page',
}))

const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({
    can: (permission: string) => canMock(permission),
    hasRole: () => false,
    roles: [],
    isLoading: false,
  }),
}))

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

const documentsSectionMock = vi.fn()
vi.mock('@/features/attachments/documents-section', () => ({
  DocumentsSection: (props: { resource: string; id: number; canUpload: boolean; canDelete: boolean }) => {
    documentsSectionMock(props)
    return <div>{`documents-section:${props.resource}:${props.id}`}</div>
  },
}))

// `opportunity_id` deliberately DIFFERENT from `id` (the row's Offerta id):
// proves the dialog is keyed on the Opportunity, not the row's own record.
const ROW: TableRow = { id: 7, opportunity_id: 99, actions: ['view', 'documents'], documents_count: 2 }

const DOCUMENTS_ACTION: TableActionDefinition = {
  key: 'documents',
  label: 'actions.documents',
  icon: 'paperclip',
  type: 'action',
  confirm: false,
  count_field: 'documents_count',
}

const refreshMock = vi.fn()

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<{ refresh: () => void }, { domain: string; onAction: RowActionHandler }>(
    function TableViewStub({ domain, onAction }, ref) {
      useImperativeHandle(ref, () => ({ refresh: refreshMock }))
      return (
        <div role="region" aria-label={`table-${domain}`}>
          <button type="button" onClick={() => onAction(DOCUMENTS_ACTION, ROW)}>
            trigger-documents
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
  canMock.mockReset()
  canMock.mockReturnValue(true)
  refreshMock.mockReset()
  documentsSectionMock.mockReset()
})

describe('RequestManagementTable — "documents" row action', () => {
  it('opens the documents dialog for the row and mounts DocumentsSection with the opportunity alias', () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'trigger-documents' }))

    expect(screen.getByRole('dialog')).toBeInTheDocument()
    expect(screen.getByText('documents-section:opportunity:99')).toBeInTheDocument()
    expect(documentsSectionMock).toHaveBeenCalledWith(
      expect.objectContaining({ resource: 'opportunity', id: 99, canUpload: true, canDelete: true }),
    )
  })

  it('refreshes the grid when the dialog closes', () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'trigger-documents' }))
    fireEvent.keyDown(screen.getByRole('dialog'), { key: 'Escape' })

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(refreshMock).toHaveBeenCalledTimes(1)
  })
})
