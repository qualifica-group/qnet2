import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { ChecklistOpenRequestsDialog } from '@/features/document-requests/checklist-open-requests-dialog'
import { buildItem, buildRequest, httpError } from '@/features/document-requests/document-request-fixtures'
import { DocumentRequestsSection } from '@/features/document-requests/document-requests-section'
import type { DocumentRequest, DocumentRequestList } from '@/features/document-requests/types'
import type { DocumentChecklistWithPermissions } from '@/features/document-checklists/types'

const fetchListMock = vi.fn<(workOrderId: number) => Promise<DocumentRequestList>>()
const refreshRulesMock = vi.fn<(id: number) => Promise<void>>()
const revalidateMock = vi.fn<(id: number, itemIds: number[]) => Promise<void>>()
const stopMock = vi.fn<(id: number, itemId: number | string) => Promise<void>>()
const countMock = vi.fn<(checklistId: number) => Promise<number>>()
const refreshOpenMock = vi.fn<(checklistId: number) => Promise<number>>()
const fetchChecklistMock = vi.fn<(id: number) => Promise<DocumentChecklistWithPermissions>>()
const toastSuccess = vi.fn()
const toastError = vi.fn()

vi.mock('@/features/document-requests/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/document-requests/api')>()),
  fetchDocumentRequests: (id: number) => fetchListMock(id),
  refreshDocumentRequestRules: (id: number) => refreshRulesMock(id),
  revalidateDocumentRequestItems: (id: number, itemIds: number[]) => revalidateMock(id, itemIds),
  stopDocumentRequestItemValidation: (id: number, itemId: number | string) => stopMock(id, itemId),
  fetchChecklistOpenRequestsCount: (id: number) => countMock(id),
  refreshChecklistOpenRequests: (id: number) => refreshOpenMock(id),
}))
vi.mock('@/features/document-checklists/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/document-checklists/api')>()),
  fetchDocumentChecklist: (id: number) => fetchChecklistMock(id),
}))
const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({ useAbilities: () => ({ can: (permission: string) => canMock(permission) }) }))
vi.mock('sonner', () => ({
  toast: { success: (message: string) => toastSuccess(message), error: (message: string) => toastError(message) },
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>{children}</ConfirmDialogProvider>
    </QueryClientProvider>
  )
}

async function renderSection(request: DocumentRequest) {
  fetchListMock.mockResolvedValue({ requests: [request], canCreate: false })
  render(<DocumentRequestsSection workOrderId={5} />, { wrapper: wrapper() })
  await screen.findByRole('article')
}

async function confirmWith(label: string) {
  const dialog = await screen.findByRole('alertdialog')
  fireEvent.click(within(dialog).getByRole('button', { name: label }))
}

const REVIEWER = { update: true, remind: true, cancel: true, review: true, complete: true, refresh_rules: true }

function checklistWith(syncStatus: 'pending' | 'synced' | 'failed') {
  return { id: 3, sync_status: syncStatus } as DocumentChecklistWithPermissions
}

describe('rules of open requests (spec 0432)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    refreshRulesMock.mockResolvedValue(undefined)
    revalidateMock.mockResolvedValue(undefined)
    stopMock.mockResolvedValue(undefined)
  })

  it('refreshes the rules of a request after the confirmation (AC-009)', async () => {
    await renderSection(buildRequest())

    fireEvent.click(screen.getByRole('button', { name: 'Aggiorna le regole dalla checklist' }))
    await confirmWith('Aggiorna le regole dalla checklist')

    await waitFor(() => expect(refreshRulesMock).toHaveBeenCalledWith(10))
    expect(toastSuccess).toHaveBeenCalledWith('Aggiornamento delle regole messo in coda.')
  })

  it('hides the refresh without permissions.refresh_rules (AC-009)', async () => {
    await renderSection(buildRequest({ permissions: { ...REVIEWER, refresh_rules: false } }))
    expect(screen.queryByRole('button', { name: 'Aggiorna le regole dalla checklist' })).not.toBeInTheDocument()
  })

  it('offers to revalidate together only the changed items rejected or to review (AC-009)', async () => {
    await renderSection(
      buildRequest({
        permissions: REVIEWER,
        rules_updated_item_ids: [1, 2, 3, 4],
        items: [
          buildItem({ id: 1, name: 'Visura', status: 'ok' }),
          buildItem({ id: 2, name: 'Statuto', status: 'ko' }),
          buildItem({ id: 3, name: 'Delega', status: 'review' }),
          buildItem({ id: 4, name: 'Bilancio', status: 'pending', documents_count: 0 }),
          buildItem({ id: 5, name: 'Atto', status: 'ko' }),
        ],
      }),
    )

    expect(screen.getByText('2 voci con regole nuove hanno un esito da rivedere.')).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Rivalida le voci cambiate' }))
    await confirmWith('Rivalida le voci cambiate')

    await waitFor(() => expect(revalidateMock).toHaveBeenCalledWith(10, [2, 3]))
  })

  it('shows no bulk revalidation without changed items or without review (AC-009)', async () => {
    await renderSection(buildRequest({ permissions: { ...REVIEWER, review: false }, rules_updated_item_ids: [2] }))
    expect(screen.queryByRole('button', { name: 'Rivalida le voci cambiate' })).not.toBeInTheDocument()
  })

  it('revalidates one item and warns that a manual decision is lost (AC-010)', async () => {
    await renderSection(buildRequest({ permissions: REVIEWER, items: [buildItem({ id: 7, name: 'Visura', status: 'approved_manually' })] }))

    fireEvent.click(screen.getByRole('button', { name: 'Rivalida con AI' }))
    const dialog = await screen.findByRole('alertdialog')
    expect(within(dialog).getByText(/la decisione presa a mano sulla voce verrà persa/)).toBeInTheDocument()
    fireEvent.click(within(dialog).getByRole('button', { name: 'Rivalida con AI' }))

    await waitFor(() => expect(revalidateMock).toHaveBeenCalledWith(10, [7]))
  })

  it('shows the 422 reason of a revalidation (AC-010)', async () => {
    revalidateMock.mockRejectedValueOnce(httpError(422, { errors: { document: ['La voce non ha nessun file caricato'] } }))
    await renderSection(buildRequest({ permissions: REVIEWER, items: [buildItem({ status: 'ko' })] }))

    fireEvent.click(screen.getByRole('button', { name: 'Rivalida con AI' }))
    await confirmWith('Rivalida con AI')

    await waitFor(() => expect(toastError).toHaveBeenCalledWith('La voce non ha nessun file caricato'))
  })

  it.each(['uploaded', 'validating'])('stops the validation of an item %s (AC-010)', async (status) => {
    await renderSection(buildRequest({ permissions: REVIEWER, items: [buildItem({ id: 8, status })] }))

    fireEvent.click(screen.getByRole('button', { name: 'Interrompi validazione' }))
    await confirmWith('Interrompi validazione')

    await waitFor(() => expect(stopMock).toHaveBeenCalledWith(10, 8))
  })

  it('offers neither revalidation nor stop on an item without files, or without review (AC-010)', async () => {
    await renderSection(
      buildRequest({ permissions: REVIEWER, items: [buildItem({ status: 'pending', documents_count: 0 })] }),
    )
    expect(screen.queryByRole('button', { name: 'Rivalida con AI' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Interrompi validazione' })).not.toBeInTheDocument()
  })
})

describe('open requests after a checklist save (spec 0432 AC-008)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    refreshOpenMock.mockResolvedValue(2)
    canMock.mockReturnValue(true)
  })

  function renderDialog(onClose = vi.fn()) {
    render(<ChecklistOpenRequestsDialog checklistId={3} onClose={onClose} />, { wrapper: wrapper() })
    return onClose
  }

  it('asks with the number of open requests and refreshes them once the checklist is sent', async () => {
    countMock.mockResolvedValue(2)
    fetchChecklistMock.mockResolvedValue(checklistWith('synced'))
    const onClose = renderDialog()

    const dialog = await screen.findByRole('dialog')
    expect(within(dialog).getByText(/Ci sono 2 richieste aperte/)).toBeInTheDocument()
    const refresh = within(dialog).getByRole('button', { name: 'Aggiorna le regole delle richieste aperte' })
    await waitFor(() => expect(refresh).toBeEnabled())
    fireEvent.click(refresh)

    await waitFor(() => expect(refreshOpenMock).toHaveBeenCalledWith(3))
    expect(toastSuccess).toHaveBeenCalledWith('Aggiornamento messo in coda per 2 richieste.')
    expect(onClose).toHaveBeenCalled()
  })

  it('keeps the refresh disabled while the checklist is being sent, or when it failed', async () => {
    countMock.mockResolvedValue(1)
    fetchChecklistMock.mockResolvedValue(checklistWith('failed'))
    renderDialog()

    const dialog = await screen.findByRole('dialog')
    expect(await within(dialog).findByRole('alert')).toHaveTextContent('non è riuscito')
    expect(within(dialog).getByRole('button', { name: 'Aggiorna le regole delle richieste aperte' })).toBeDisabled()
  })

  it('shows the wait while the checklist is pending', async () => {
    countMock.mockResolvedValue(1)
    fetchChecklistMock.mockResolvedValue(checklistWith('pending'))
    renderDialog()

    const dialog = await screen.findByRole('dialog')
    expect(await within(dialog).findByText('Invio della checklist ad ArchivioSmart in corso…')).toBeInTheDocument()
    expect(within(dialog).getByRole('button', { name: 'Aggiorna le regole delle richieste aperte' })).toBeDisabled()
  })

  it('"Solo per le nuove richieste" closes without any call', async () => {
    countMock.mockResolvedValue(1)
    fetchChecklistMock.mockResolvedValue(checklistWith('synced'))
    const onClose = renderDialog()

    fireEvent.click(await screen.findByRole('button', { name: 'Solo per le nuove richieste' }))

    expect(onClose).toHaveBeenCalled()
    expect(refreshOpenMock).not.toHaveBeenCalled()
  })

  it('does not ask a user without document-requests.update', async () => {
    canMock.mockReturnValue(false)
    renderDialog()

    await waitFor(() => expect(canMock).toHaveBeenCalledWith('document-requests.update'))
    expect(countMock).not.toHaveBeenCalled()
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('shows nothing when the checklist has no open request', async () => {
    countMock.mockResolvedValue(0)
    renderDialog()

    await waitFor(() => expect(countMock).toHaveBeenCalledWith(3))
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(fetchChecklistMock).not.toHaveBeenCalled()
  })
})
