import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { seedLineStatusCatalog } from '@/features/purchase-request-line-statuses/line-status-catalog-fixtures'
import { PurchaseRequestDetailView } from '@/features/purchase-requests/purchase-request-detail'
import { makeLine, makeRequest } from '@/features/purchase-requests/purchase-request-fixtures'
import type { PurchaseRequest, PurchaseRequestPayload } from '@/features/purchase-requests/types'

// Spec 0312: the notes tab mounts the agnostic NotesSection, covered by `notes-section.test.tsx`.
// REQUIREMENT CHANGED (spec 0312): the Notes tab panel is labelled "Notes" too, so the Notes
// FIELD is addressed by its textbox role.
vi.mock('@/features/notes/notes-section', () => ({
  NotesSection: (props: { entityType: string; entityId: number }) => (
    <div>{`notes-section:${props.entityType}:${props.entityId}`}</div>
  ),
}))

vi.mock('@/features/for-select/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/for-select/api')>()),
  fetchForSelect: vi.fn(async () => ({
    items: [],
    export_link: null,
    pagination: { total: 0, offset: 0, limit: 25, total_pages: 1 },
  })),
}))

const updateMock = vi.fn<(id: number, payload: Partial<PurchaseRequestPayload>) => Promise<PurchaseRequest>>()
vi.mock('@/features/purchase-requests/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/purchase-requests/api')>()),
  updatePurchaseRequest: (id: number, payload: Partial<PurchaseRequestPayload>) => updateMock(id, payload),
  fetchApproverOptions: vi.fn(async () => ({ source: 'reports_to', approvers: [{ id: 4, name: 'Anna Verdi' }] })),
}))

vi.mock('@/features/attachments/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/attachments/api')>()),
  listAttachments: vi.fn().mockResolvedValue([]),
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn() } }))

const NO_UPDATE = { update: false, delete: false, close: false, notify_manager: false, view_activity: true }

function renderDetail(request: PurchaseRequest = makeRequest()) {
  const client = seedLineStatusCatalog(new QueryClient({ defaultOptions: { queries: { retry: false } } }))
  return render(
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>
        <PurchaseRequestDetailView request={request} onBack={vi.fn()} onDeleted={vi.fn()} />
      </ConfirmDialogProvider>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  updateMock.mockReset().mockImplementation(async () => makeRequest({ subject: 'Saved' }))
})

describe('RDA detail header, edited in place (spec 0256 AC-006)', () => {
  it('has no form Save button and shows the saved values', () => {
    renderDetail()

    expect(screen.queryByRole('button', { name: 'Save' })).not.toBeInTheDocument()
    expect(screen.getAllByText('Office equipment').length).toBeGreaterThan(0)
    expect(screen.getByText('Qualifica Srl')).toBeInTheDocument()
  })

  it('saves only the edited field', async () => {
    renderDetail()

    fireEvent.click(screen.getByRole('button', { name: 'Edit Subject' }))
    fireEvent.change(screen.getByLabelText(/^Subject/), { target: { value: 'Monitors' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateMock).toHaveBeenCalledWith(5, { subject: 'Monitors' }))
  })

  it('cancels without calling the server', () => {
    renderDetail()

    fireEvent.click(screen.getByRole('button', { name: 'Edit Notes' }))
    fireEvent.change(screen.getByRole('textbox', { name: /^Notes/ }), { target: { value: 'Draft' } })
    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))

    expect(updateMock).not.toHaveBeenCalled()
    expect(screen.queryByRole('textbox', { name: /^Notes/ })).not.toBeInTheDocument()
  })

  it('applies the field permissions: a read-only company has no pencil', () => {
    renderDetail(makeRequest({ field_permissions: { company_id: { visible: true, editable: false, required: true } } }))

    expect(screen.queryByRole('button', { name: 'Edit Company' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Edit Operational site' })).toBeInTheDocument()
  })

  it('shows no pencil at all on a closed RDA', () => {
    renderDetail(makeRequest({ status: 'closed', abilities: NO_UPDATE }))

    expect(screen.queryAllByRole('button', { name: /^Edit / })).toHaveLength(0)
    expect(screen.queryByRole('button', { name: 'Add line' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Remove line 1' })).not.toBeInTheDocument()
  })
})

describe('RDA detail lines, one at a time (spec 0256 AC-007)', () => {
  it('shows a saved line read-only until its pencil opens it', () => {
    renderDetail()

    expect(screen.queryByLabelText('Description 1')).not.toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Edit line 1' }))

    expect(screen.getByLabelText('Description 1')).toBeEnabled()
  })

  it('saves the edited line with the whole lines set', async () => {
    renderDetail(makeRequest({ lines: [makeLine(), makeLine({ id: 12, position: 2, description: 'Mouse' })] }))

    fireEvent.click(screen.getByRole('button', { name: 'Edit line 1' }))
    fireEvent.change(screen.getByLabelText('Description 1'), { target: { value: 'Desk' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateMock).toHaveBeenCalledTimes(1))
    const [, payload] = updateMock.mock.calls[0]!
    expect(Object.keys(payload)).toEqual(['lines'])
    expect(payload.lines?.map((line) => [line.id, line.description])).toEqual([
      [11, 'Desk'],
      [12, 'Mouse'],
    ])
  })

  it('discards the line edit on Cancel without calling the server', () => {
    renderDetail()

    fireEvent.click(screen.getByRole('button', { name: 'Edit line 1' }))
    fireEvent.change(screen.getByLabelText('Description 1'), { target: { value: 'Desk' } })
    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))

    expect(updateMock).not.toHaveBeenCalled()
    expect(screen.getByText('Laptop')).toBeInTheDocument()
  })

  it('opens a new line in edit mode and drops it on Cancel', () => {
    renderDetail()

    fireEvent.click(screen.getByRole('button', { name: 'Add line' }))
    expect(screen.getByLabelText('Description 2')).toBeEnabled()

    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))
    expect(screen.queryByRole('listitem', { name: 'Line 2' })).not.toBeInTheDocument()
  })

  it('edits and deletes only where the line allows it', () => {
    renderDetail(
      makeRequest({
        lines: [
          makeLine({ id: 11, description: 'Pending line' }),
          makeLine({
            id: 12,
            position: 2,
            description: 'Ordered line',
            status: 'ordered',
            abilities: { update: false, delete: false, transitions: ['received'], capabilities: [], update_supplier: false },
          }),
          makeLine({
            id: 13,
            position: 3,
            description: 'Rejected line',
            status: 'rejected',
            abilities: { update: false, delete: true, transitions: [], capabilities: [], update_supplier: false },
          }),
        ],
      }),
    )

    expect(screen.getByRole('button', { name: 'Edit line 1' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit line 2' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit line 3' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Remove line 1' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Remove line 2' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Remove line 3' })).toBeInTheDocument()
  })

  it('deletes a line after confirmation', async () => {
    renderDetail(makeRequest({ lines: [makeLine(), makeLine({ id: 12, position: 2, description: 'Mouse' })] }))

    fireEvent.click(screen.getByRole('button', { name: 'Remove line 1' }))
    fireEvent.click(await screen.findByRole('button', { name: 'Delete line' }))

    await waitFor(() => expect(updateMock).toHaveBeenCalledTimes(1))
    expect(updateMock.mock.calls[0]![1].lines?.map((line) => line.id)).toEqual([12])
  })

  it('shows the line status and the ODA placeholder as plain read-only text', () => {
    renderDetail(makeRequest({ lines: [makeLine({ status: 'approved' })] }))

    const line = screen.getByRole('listitem', { name: 'Line 1' })
    expect(within(line).getByText('Approved')).toBeInTheDocument()
    expect(screen.getByText('ODA')).toBeInTheDocument()
    expect(within(line).getAllByText('—').length).toBeGreaterThan(0)
  })

  it('offers the status change on a saved line only when the server allows a transition', () => {
    renderDetail(
      makeRequest({
        lines: [
          makeLine({ id: 21 }),
          makeLine({ id: 22, position: 2, abilities: { update: true, delete: true, transitions: [], capabilities: [], update_supplier: false } }),
        ],
      }),
    )

    const second = screen.getByRole('listitem', { name: 'Line 2' })
    expect(within(second).queryByRole('button', { name: 'Change line status' })).not.toBeInTheDocument()

    fireEvent.click(within(screen.getByRole('listitem', { name: 'Line 1' })).getByRole('button', { name: 'Change line status' }))
    const dialog = screen.getByRole('dialog')
    expect(within(dialog).getByText('Approved')).toBeInTheDocument()
    expect(within(dialog).getByText('Rejected')).toBeInTheDocument()
  })

  it('extracts the VAT from the unit price inside the open line (122.00 at 22% -> 100.00)', () => {
    renderDetail(makeRequest({ lines: [makeLine({ unit_price: '122.00', quantity: '1.000' })] }))

    fireEvent.click(screen.getByRole('button', { name: 'Edit line 1' }))
    fireEvent.click(screen.getByRole('button', { name: 'Extract the VAT from the price 1' }))

    expect(screen.getByLabelText('Unit price 1')).toHaveValue(100)
  })
})

describe('RDA detail notes tab (spec 0312)', () => {
  it('shows the Notes tab on the purchase-requests thread of the record', () => {
    renderDetail()

    expect(screen.getByRole('tab', { name: 'Notes' })).toHaveAttribute('aria-selected', 'true')
    expect(screen.getByText('notes-section:purchase-requests:5')).toBeInTheDocument()
  })
})
