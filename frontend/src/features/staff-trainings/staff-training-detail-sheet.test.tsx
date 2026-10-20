import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { StaffTrainingDetailSheet } from '@/features/staff-trainings/staff-training-detail-sheet'
import { buildTraining, trainingWrapper } from '@/features/staff-trainings/staff-training-fixtures'
import type { StaffTraining } from '@/features/staff-trainings/types'

const permissions = new Set<string>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => permissions.has(permission), hasRole: () => false, roles: [], isLoading: false }),
}))

const fetchMock = vi.fn()
const deleteMock = vi.fn()
const downloadMock = vi.fn()
const uploadMock = vi.fn()
const removeAttachmentMock = vi.fn()
vi.mock('@/features/staff-trainings/api', () => ({
  fetchStaffTraining: (id: number) => fetchMock(id),
  deleteStaffTraining: (id: number) => deleteMock(id),
  downloadStaffTrainingAttachment: (id: number) => downloadMock(id),
  uploadStaffTrainingAttachment: (id: number, file: File) => uploadMock(id, file),
  deleteStaffTrainingAttachment: (id: number) => removeAttachmentMock(id),
}))
vi.mock('@/lib/download', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/lib/download')>()),
  saveBlob: vi.fn(),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const label = (key: string) => i18n.t(key)
const onClose = vi.fn()
const onEdit = vi.fn()
const refreshGrid = vi.fn()

function renderDetail(training: StaffTraining) {
  fetchMock.mockResolvedValue(training)
  return render(
    <StaffTrainingDetailSheet trainingId={training.id} onClose={onClose} onEdit={onEdit} refreshGrid={refreshGrid} />,
    { wrapper: trainingWrapper() },
  )
}

const button = (key: string) => screen.queryByRole('button', { name: label(key) })

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  ;[fetchMock, deleteMock, downloadMock, uploadMock, removeAttachmentMock, onClose, onEdit, refreshGrid].forEach((mock) =>
    mock.mockReset(),
  )
  permissions.clear()
  permissions.add('staff-trainings.viewAny')
})

describe('StaffTrainingDetailSheet', () => {
  it('heads the record with the course and lays the data out in sections (spec 0287)', async () => {
    renderDetail(buildTraining())

    expect(await screen.findByRole('heading', { level: 2, name: 'Sicurezza generale' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { level: 3, name: label('staffTrainings.detail.sections.training') })).toBeInTheDocument()
    expect(screen.getByRole('heading', { level: 3, name: label('staffTrainings.detail.sections.record') })).toBeInTheDocument()
    expect(screen.getByRole('heading', { level: 3, name: label('staffTrainings.detail.sections.certificate') })).toBeInTheDocument()
  })

  it('shows data, outcome with text and the computed expiry', async () => {
    renderDetail(buildTraining({ notes: 'Aula A' }))

    expect(await screen.findByText('Anna Rossi')).toBeInTheDocument()
    expect(screen.getByText('Sicurezza generale')).toBeInTheDocument()
    expect(screen.getByText(label('enums.staff_training_outcome.passed'))).toBeInTheDocument()
    expect(screen.getByText('10/03/2031')).toBeInTheDocument()
    expect(screen.getByText('60 mesi')).toBeInTheDocument()
    expect(screen.getByText('Aula A')).toBeInTheDocument()
  })

  it('says the training does not expire when the course has no validity', async () => {
    renderDetail(buildTraining({ expires_on: null, course: { id: 3, name: 'Induction', validity_months: null } }))

    expect(await screen.findByText('Induction')).toBeInTheDocument()
    expect(screen.getAllByText(label('staffTrainings.detail.neverExpires'))).toHaveLength(2)
  })

  it('lets a viewer without manage only download the certificate', async () => {
    downloadMock.mockResolvedValue({ blob: new Blob(['x']), filename: 'attestato.pdf' })
    renderDetail(buildTraining())
    await screen.findByText('Anna Rossi')

    for (const key of ['staffTrainings.actions.edit', 'staffTrainings.actions.delete', 'staffTrainings.detail.replace', 'staffTrainings.detail.remove']) {
      expect(button(key)).not.toBeInTheDocument()
    }
    expect(screen.queryByLabelText(label('staffTrainings.detail.upload'))).not.toBeInTheDocument()
    fireEvent.click(button('staffTrainings.detail.download') as HTMLElement)
    await waitFor(() => expect(downloadMock).toHaveBeenCalledWith(5))
  })

  it('offers edit, delete and certificate management with manage', async () => {
    permissions.add('staff-trainings.manage')
    renderDetail(buildTraining())
    await screen.findByText('Anna Rossi')

    for (const key of ['staffTrainings.actions.edit', 'staffTrainings.actions.delete', 'staffTrainings.detail.replace', 'staffTrainings.detail.remove', 'staffTrainings.detail.download']) {
      expect(button(key)).toBeInTheDocument()
    }
  })

  it('uploads a certificate when none is attached and refreshes the grid', async () => {
    permissions.add('staff-trainings.manage')
    uploadMock.mockResolvedValue(buildTraining())
    renderDetail(buildTraining({ attachment: null }))
    await screen.findByText(label('staffTrainings.detail.noAttachment'))

    const file = new File(['x'], 'attestato.pdf', { type: 'application/pdf' })
    fireEvent.change(screen.getByLabelText(label('staffTrainings.detail.upload')), { target: { files: [file] } })

    await waitFor(() => expect(uploadMock).toHaveBeenCalledWith(5, file))
    await waitFor(() => expect(refreshGrid).toHaveBeenCalled())
  })

  it('removes the certificate', async () => {
    permissions.add('staff-trainings.manage')
    removeAttachmentMock.mockResolvedValue(undefined)
    renderDetail(buildTraining())
    await screen.findByText('Anna Rossi')

    fireEvent.click(button('staffTrainings.detail.remove') as HTMLElement)
    await waitFor(() => expect(removeAttachmentMock).toHaveBeenCalledWith(5))
  })

  it('deletes only after the confirmation, then closes and refreshes the grid', async () => {
    permissions.add('staff-trainings.manage')
    deleteMock.mockResolvedValue(undefined)
    renderDetail(buildTraining())
    await screen.findByText('Anna Rossi')

    fireEvent.click(button('staffTrainings.actions.delete') as HTMLElement)
    expect(deleteMock).not.toHaveBeenCalled()
    const dialog = await screen.findByRole('alertdialog')
    fireEvent.click(Array.from(dialog.querySelectorAll('button')).find((b) => b.textContent === label('staffTrainings.actions.delete')) as HTMLElement)

    await waitFor(() => expect(deleteMock).toHaveBeenCalledWith(5))
    await waitFor(() => expect(onClose).toHaveBeenCalled())
    expect(refreshGrid).toHaveBeenCalled()
  })

  it('opens the edit form from the detail with manage', async () => {
    permissions.add('staff-trainings.manage')
    const training = buildTraining()
    renderDetail(training)
    await screen.findByText('Anna Rossi')

    fireEvent.click(button('staffTrainings.actions.edit') as HTMLElement)
    expect(onEdit).toHaveBeenCalledWith(training)
  })

  it('shows the not-found message when the training is out of visibility', async () => {
    fetchMock.mockRejectedValue(Object.assign(new Error('nf'), { isAxiosError: true, response: { status: 404 } }))
    render(<StaffTrainingDetailSheet trainingId={9} onClose={onClose} onEdit={onEdit} />, { wrapper: trainingWrapper() })

    expect(await screen.findByRole('alert')).toBeInTheDocument()
  })
})
