import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { AbsenceRequestDetailSheet } from '@/features/absences/requests/absence-request-detail-sheet'
import { absenceWrapper, buildRequest, httpError } from '@/features/absences/requests/absence-request-fixtures'
import type { AbsenceRequest } from '@/features/absences/types'

const fetchMock = vi.fn()
const approveMock = vi.fn()
const rejectMock = vi.fn()
const cancelMock = vi.fn()
const submitMock = vi.fn()
const deleteMock = vi.fn()
const downloadMock = vi.fn()
vi.mock('@/features/absences/api', () => ({
  fetchAbsenceRequest: (id: number) => fetchMock(id),
  approveAbsenceRequest: (id: number) => approveMock(id),
  rejectAbsenceRequest: (id: number, payload: unknown) => rejectMock(id, payload),
  cancelAbsenceRequest: (id: number) => cancelMock(id),
  submitAbsenceRequest: (id: number) => submitMock(id),
  deleteAbsenceRequest: (id: number) => deleteMock(id),
  downloadAbsenceAttachment: (id: number) => downloadMock(id),
  uploadAbsenceAttachment: vi.fn(),
  deleteAbsenceAttachment: vi.fn(),
}))

vi.mock('@/features/absences/balances/absence-balances-card', () => ({
  AbsenceBalancesCard: ({ userId }: { userId?: number }) => <div role="region" aria-label={`balances-${userId}`} />,
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const label = (key: string) => i18n.t(key)
const onClose = vi.fn()
const onEdit = vi.fn()
const refreshGrid = vi.fn()

function renderDetail(request: AbsenceRequest) {
  fetchMock.mockResolvedValue(request)
  return render(
    <AbsenceRequestDetailSheet requestId={request.id} onClose={onClose} onEdit={onEdit} refreshGrid={refreshGrid} />,
    { wrapper: absenceWrapper() },
  )
}

const action = (key: string) => screen.queryByRole('button', { name: label(`absences.requests.actions.${key}`) })

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  ;[fetchMock, approveMock, rejectMock, cancelMock, submitMock, deleteMock, downloadMock, onClose, onEdit, refreshGrid].forEach(
    (mock) => mock.mockReset(),
  )
})

describe('AbsenceRequestDetailSheet', () => {
  it('heads the record with the absence type and lays the data out in sections (spec 0287)', async () => {
    renderDetail(buildRequest())

    expect(await screen.findByRole('heading', { level: 2, name: 'Ferie' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { level: 3, name: label('absences.requests.detail.sections.request') })).toBeInTheDocument()
    expect(screen.getByRole('heading', { level: 3, name: label('absences.requests.detail.sections.approval') })).toBeInTheDocument()
    expect(screen.getByRole('heading', { level: 3, name: label('absences.requests.detail.sections.attachment') })).toBeInTheDocument()
  })

  it('shows the data, the approvers and the status of the request', async () => {
    renderDetail(buildRequest({ note: 'Mare' }))

    expect(await screen.findByText('Anna Rossi')).toBeInTheDocument()
    expect(screen.getByText('Bruno Neri')).toBeInTheDocument()
    expect(screen.getByText('Mare')).toBeInTheDocument()
    expect(screen.getByText(label('absences.status.submitted'))).toBeInTheDocument()
    expect(screen.getByText('24h 00m')).toBeInTheDocument()
  })

  it('shows no action when the server grants none', async () => {
    renderDetail(buildRequest())
    await screen.findByText('Anna Rossi')

    for (const key of ['edit', 'delete', 'submit', 'approve', 'reject', 'cancel']) {
      expect(action(key)).not.toBeInTheDocument()
    }
    expect(screen.queryByRole('region', { name: 'balances-7' })).not.toBeInTheDocument()
  })

  it('shows exactly the actions of `can` for a draft', async () => {
    renderDetail(buildRequest({ status: 'draft', minutes: null, can: { ...buildRequest().can, update: true, delete: true, submit: true, attach: true } }))
    await screen.findByText('Anna Rossi')

    expect(action('edit')).toBeInTheDocument()
    expect(action('delete')).toBeInTheDocument()
    expect(action('submit')).toBeInTheDocument()
    expect(action('approve')).not.toBeInTheDocument()
    expect(screen.getByLabelText(label('absences.requests.detail.upload'))).toBeInTheDocument()
  })

  it('approves and refreshes the grid, with the balances of the person for the approver', async () => {
    approveMock.mockResolvedValue(buildRequest({ status: 'approved' }))
    renderDetail(buildRequest({ can: { ...buildRequest().can, approve: true, reject: true } }))

    expect(await screen.findByRole('region', { name: 'balances-7' })).toBeInTheDocument()
    fireEvent.click(action('approve')!)

    await waitFor(() => expect(approveMock).toHaveBeenCalledWith(5))
    await waitFor(() => expect(refreshGrid).toHaveBeenCalled())
  })

  it('requires the rejection reason and wires the server 422 to the field', async () => {
    renderDetail(buildRequest({ can: { ...buildRequest().can, reject: true } }))
    await screen.findByText('Anna Rossi')
    fireEvent.click(action('reject')!)

    const confirm = await screen.findByRole('button', { name: label('absences.requests.reject.confirm') })
    fireEvent.click(confirm)
    const required = await screen.findByText(label('absences.requests.reject.reasonRequired'))
    expect(required).toHaveAttribute('role', 'alert')
    expect(rejectMock).not.toHaveBeenCalled()

    rejectMock.mockRejectedValue(httpError(422, { message: 'Invalid', errors: { rejection_reason: ['Motivo troppo lungo.'] } }))
    const reason = screen.getByLabelText(new RegExp(label('absences.requests.reject.reason')))
    fireEvent.change(reason, { target: { value: 'Periodo scoperto' } })
    fireEvent.click(confirm)

    const serverAlert = await screen.findByText('Motivo troppo lungo.')
    expect(serverAlert).toHaveAttribute('role', 'alert')
    expect(reason).toHaveAttribute('aria-invalid', 'true')
    expect(reason.getAttribute('aria-describedby')).toContain(serverAlert.id)
    expect(rejectMock).toHaveBeenCalledWith(5, { rejection_reason: 'Periodo scoperto' })
  })

  it('rejects with the reason and closes the dialog', async () => {
    rejectMock.mockResolvedValue(buildRequest({ status: 'rejected' }))
    renderDetail(buildRequest({ can: { ...buildRequest().can, reject: true } }))
    await screen.findByText('Anna Rossi')
    fireEvent.click(action('reject')!)

    fireEvent.change(await screen.findByLabelText(new RegExp(label('absences.requests.reject.reason'))), {
      target: { value: 'Periodo scoperto' },
    })
    fireEvent.click(screen.getByRole('button', { name: label('absences.requests.reject.confirm') }))

    await waitFor(() => expect(rejectMock).toHaveBeenCalledWith(5, { rejection_reason: 'Periodo scoperto' }))
    await waitFor(() => expect(screen.queryByText(label('absences.requests.reject.title'))).not.toBeInTheDocument())
  })

  it('asks for confirmation before cancelling', async () => {
    cancelMock.mockResolvedValue(buildRequest({ status: 'cancelled' }))
    renderDetail(buildRequest({ can: { ...buildRequest().can, cancel: true } }))
    await screen.findByText('Anna Rossi')

    fireEvent.click(action('cancel')!)
    expect(cancelMock).not.toHaveBeenCalled()
    expect(await screen.findByText(label('absences.requests.actions.cancelTitle'))).toBeInTheDocument()
    fireEvent.click(await screen.findByRole('button', { name: label('absences.requests.actions.cancel') }))

    await waitFor(() => expect(cancelMock).toHaveBeenCalledWith(5))
  })

  it('hands the draft to the editor', async () => {
    const draft = buildRequest({ status: 'draft', can: { ...buildRequest().can, update: true } })
    renderDetail(draft)
    await screen.findByText('Anna Rossi')

    fireEvent.click(action('edit')!)
    expect(onEdit).toHaveBeenCalledWith(draft)
  })

  it('says so when the request is not visible', async () => {
    fetchMock.mockRejectedValue(httpError(404, { message: 'Not found' }))
    render(<AbsenceRequestDetailSheet requestId={99} onClose={onClose} onEdit={onEdit} />, { wrapper: absenceWrapper() })

    expect(await screen.findByText(label('absences.requests.detail.notFound'))).toBeInTheDocument()
  })
})
