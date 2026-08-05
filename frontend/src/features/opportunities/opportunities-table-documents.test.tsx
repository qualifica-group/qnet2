import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { OpportunitiesTable } from '@/features/opportunities/opportunities-table'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * The "documents" row action (attachments row action): clicking it opens the
 * shared `DocumentsDialog` for that row's opportunity, and closing it
 * refreshes the grid so the `documents_count` badge stays current. The
 * generic `<TableView>` is stubbed (its own behavior is covered elsewhere);
 * `DocumentsSection` is stubbed too since its own upload/delete flow is
 * covered by `documents-section.test.tsx` — this suite is only about what the
 * opportunities adapter does with the action and the dialog's open state.
 */

vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'modal',
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

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const deleteOpportunityMock = vi.fn()
vi.mock('@/features/opportunities/api', () => ({
  deleteOpportunity: (...args: unknown[]) => deleteOpportunityMock(...args),
  OPPORTUNITIES_DOMAIN: 'opportunities',
  OPPORTUNITY_ATTACHABLE_ALIAS: 'opportunity',
}))

const documentsSectionMock = vi.fn()
vi.mock('@/features/attachments/documents-section', () => ({
  DocumentsSection: (props: { resource: string; id: number; canUpload: boolean; canDelete: boolean }) => {
    documentsSectionMock(props)
    return <div>{`documents-section:${props.resource}:${props.id}`}</div>
  },
}))

const ROW: TableRow = { id: 42, actions: ['view', 'documents', 'notes', 'delete'], documents_count: 3 }

function action(key: string): TableActionDefinition {
  return {
    key,
    label: `actions.${key}`,
    icon: 'eye',
    type: key === 'delete' ? 'danger' : 'action',
    confirm: key === 'delete',
    count_field: key === 'documents' ? 'documents_count' : null,
  }
}

const refreshMock = vi.fn()
let capturedOnAction: RowActionHandler | null = null

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<{ refresh: () => void }, { domain: string; onAction: RowActionHandler }>(
    function TableViewStub({ domain, onAction }, ref) {
      useImperativeHandle(ref, () => ({ refresh: refreshMock }))
      capturedOnAction = onAction
      return (
        <div role="region" aria-label={`table-${domain}`}>
          <button type="button" onClick={() => onAction(action('documents'), ROW)}>
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
        <OpportunitiesTable />
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
  deleteOpportunityMock.mockReset()
  documentsSectionMock.mockReset()
  capturedOnAction = null
})

describe('OpportunitiesTable — "documents" row action', () => {
  it('opens the documents dialog for the row and mounts DocumentsSection with the opportunity alias', () => {
    renderTable()

    expect(capturedOnAction).not.toBeNull()
    fireEvent.click(screen.getByRole('button', { name: 'trigger-documents' }))

    expect(screen.getByRole('dialog')).toBeInTheDocument()
    expect(screen.getByText('documents-section:opportunity:42')).toBeInTheDocument()
    expect(documentsSectionMock).toHaveBeenCalledWith(
      expect.objectContaining({ resource: 'opportunity', id: 42, canUpload: true, canDelete: true }),
    )
  })

  it('refreshes the grid when the dialog closes', () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'trigger-documents' }))
    expect(screen.getByRole('dialog')).toBeInTheDocument()

    fireEvent.keyDown(screen.getByRole('dialog'), { key: 'Escape' })

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(refreshMock).toHaveBeenCalledTimes(1)
  })
})
