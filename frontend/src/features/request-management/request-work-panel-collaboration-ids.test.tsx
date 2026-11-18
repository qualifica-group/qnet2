import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { RequestWorkPanelScreen } from '@/features/request-management/request-work-panel'
import { workPanel as panel } from '@/features/request-management/request-work-panel-fixtures'

/**
 * Spec 0086 D-9/D-10: the panel's own record-level id split. Notes and
 * (`request-work-panel.test.tsx`'s own suite) activity history stay anchored
 * to the underlying Opportunity (`panel.opportunity_id`); the documents are
 * the Offerta's own (`panel.id`, REQUIREMENT CHANGED by spec 0415 D-3), the
 * Opportunity's shown read-only in their own tab;
 * field-change-request proposals key on the Offerta itself (`panel.id`,
 * D-10 revised, superseding the D-9 wording in the original spec draft). The
 * shared fixture deliberately sets these two ids to DIFFERENT values
 * (`request-work-panel-fixtures.ts`) so an inverted wiring fails these
 * assertions instead of passing by coincidence.
 *
 * Le note portano ENTRAMBI gli id (direttiva utente 2026-08-07): thread
 * dell'Opportunita' + `lockedQuoteId` sull'Offerta, come il dettaglio Offerta.
 */

const fetchRequestWorkPanelMock = vi.fn()
vi.mock('@/features/request-management/api', () => ({
  fetchRequestWorkPanel: (...args: unknown[]) => fetchRequestWorkPanelMock(...args),
  updateRequestWork: vi.fn(),
}))

vi.mock('@/features/personal-data/api', () => ({
  createContact: vi.fn(),
  updateContact: vi.fn(),
  deleteContact: vi.fn(),
}))

vi.mock('@/features/personal-data/contact-form', () => ({
  ContactForm: () => null,
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('@/features/product-categories/use-product-category-tree', () => ({
  useProductCategoryTree: () => ({ data: [], isPending: false, isError: false, refetch: vi.fn() }),
}))

const notesSectionMock = vi.fn()
vi.mock('@/features/notes/notes-section', () => ({
  NotesSection: (props: { entityType: string; entityId: number; lockedQuoteId?: number | null }) => {
    notesSectionMock(props)
    return <div>{`notes-section:${props.entityType}:${props.entityId}:${props.lockedQuoteId}`}</div>
  },
}))

const documentsSectionMock = vi.fn()
vi.mock('@/features/attachments/documents-section', () => ({
  DocumentsSection: (props: { resource: string; id: number }) => {
    documentsSectionMock(props)
    return <div>{`documents-section:${props.resource}:${props.id}`}</div>
  },
}))

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: () => null,
}))

const fetchFieldChangeRequestsForRecordMock = vi.fn()
vi.mock('@/features/field-change-requests/api', () => ({
  fetchFieldChangeRequestsForRecord: (...args: unknown[]) => fetchFieldChangeRequestsForRecordMock(...args),
  approveFieldChangeRequest: vi.fn(),
  rejectFieldChangeRequest: vi.fn(),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

function renderPanel() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>
        <RequestWorkPanelScreen id={4001} />
      </ConfirmDialogProvider>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchRequestWorkPanelMock.mockReset()
  fetchRequestWorkPanelMock.mockResolvedValue(panel())
  notesSectionMock.mockReset()
  documentsSectionMock.mockReset()
  fetchFieldChangeRequestsForRecordMock.mockReset()
  fetchFieldChangeRequestsForRecordMock.mockResolvedValue([])
})

describe('RequestWorkPanelScreen — collaboration ids (spec 0086 D-9)', () => {
  it('keys Notes on the Opportunity id, never the Offerta id, and locks the thread on the Offerta', async () => {
    renderPanel()

    expect(
      await screen.findByText(`notes-section:request-management:${panel().opportunity_id}:${panel().id}`),
    ).toBeInTheDocument()
    expect(notesSectionMock).toHaveBeenCalledWith(
      expect.objectContaining({
        entityType: 'request-management',
        entityId: panel().opportunity_id,
        // Direttiva utente 2026-08-07: stesso filtro del dettaglio Offerta —
        // il thread e' dell'Opportunita', la lista e' di QUESTA Offerta.
        lockedQuoteId: panel().id,
      }),
    )
    expect(notesSectionMock).not.toHaveBeenCalledWith(expect.objectContaining({ entityId: panel().id }))
  })

  it('keys Documents on the Offerta id, editable (spec 0415 D-3)', async () => {
    renderPanel()

    // Radix `TabsTrigger` activates on `mouseDown`, not `click`.
    fireEvent.mouseDown(await screen.findByRole('tab', { name: 'Documents' }))

    expect(await screen.findByText(`documents-section:quote:${panel().id}`)).toBeInTheDocument()
    expect(documentsSectionMock).toHaveBeenCalledWith(
      expect.objectContaining({ resource: 'quote', id: panel().id, canUpload: true, canDelete: true }),
    )
  })

  it('shows the Opportunity documents read-only in their own tab (spec 0415 D-3)', async () => {
    renderPanel()

    fireEvent.mouseDown(await screen.findByRole('tab', { name: 'Opportunity documents' }))

    expect(await screen.findByText(`documents-section:opportunity:${panel().opportunity_id}`)).toBeInTheDocument()
    expect(documentsSectionMock).toHaveBeenCalledWith(
      expect.objectContaining({ resource: 'opportunity', id: panel().opportunity_id, canUpload: false, canDelete: false }),
    )
  })
})

describe('RequestWorkPanelScreen — field-change-request subject id (spec 0086 D-10)', () => {
  it('lists proposals for the Offerta id, never the Opportunity id', async () => {
    renderPanel()

    await waitFor(() =>
      expect(fetchFieldChangeRequestsForRecordMock).toHaveBeenCalledWith('request-management', panel().id),
    )
    expect(fetchFieldChangeRequestsForRecordMock).not.toHaveBeenCalledWith(
      'request-management',
      panel().opportunity_id,
    )
  })
})
