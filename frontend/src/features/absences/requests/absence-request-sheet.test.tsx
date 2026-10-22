import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { AbsenceRequestSheet } from '@/features/absences/requests/absence-request-sheet'
import { absenceWrapper, buildInsight, buildRequest, buildTypeOption, httpError } from '@/features/absences/requests/absence-request-fixtures'

const createMock = vi.fn()
const updateMock = vi.fn()
const submitMock = vi.fn()
const uploadMock = vi.fn()
const typeOptionsMock = vi.fn()
const previewMock = vi.fn()
const canMock = vi.fn<(permission: string) => boolean>()
const TYPE_OPTIONS = [
  buildTypeOption(),
  buildTypeOption({ id: 2, name: 'Malattia', deducts_balance: false, allows_half_day: false, allows_hourly: false }),
]
vi.mock('@/features/absences/api', () => ({
  fetchAbsenceTypeOptions: () => typeOptionsMock(),
  previewAbsenceRequest: (payload: unknown) => previewMock(payload),
  createAbsenceRequest: (payload: unknown) => createMock(payload),
  updateAbsenceRequest: (id: number, payload: unknown) => updateMock(id, payload),
  submitAbsenceRequest: (id: number) => submitMock(id),
  uploadAbsenceAttachment: (id: number, file: File) => uploadMock(id, file),
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: canMock, hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({ labels, onChange }: { labels: { triggerLabel: string }; onChange: (id: number) => void }) => (
    <button type="button" onClick={() => onChange(9)}>
      {labels.triggerLabel}
    </button>
  ),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const label = (key: string) => i18n.t(key)
const onSaved = vi.fn()

function renderSheet(request?: ReturnType<typeof buildRequest>) {
  return render(<AbsenceRequestSheet open onOpenChange={vi.fn()} request={request} onSaved={onSaved} />, {
    wrapper: absenceWrapper(),
  })
}

async function pickType(name: string) {
  fireEvent.keyDown(await screen.findByRole('combobox', { name: new RegExp(label('absences.requests.form.type')) }), {
    key: 'ArrowDown',
  })
  fireEvent.click(await screen.findByRole('option', { name }))
}

/** Anchored: a required label also renders a trailing "*", and "Al" is a prefix of "Allegato". */
const labelPattern = (key: string) =>
  new RegExp(`^${label(key).replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\s*\\*?$`)
const modeRadio = (key: string) => screen.getByRole('radio', { name: label(key) })
const dateField = (key: string) => screen.getByLabelText(labelPattern(key))

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  createMock.mockReset()
  updateMock.mockReset()
  submitMock.mockReset()
  uploadMock.mockReset()
  typeOptionsMock.mockReset()
  typeOptionsMock.mockResolvedValue(TYPE_OPTIONS)
  previewMock.mockReset()
  previewMock.mockResolvedValue(buildInsight())
  canMock.mockReset()
  canMock.mockReturnValue(false)
  onSaved.mockReset()
})

describe('AbsenceRequestSheet', () => {
  it('groups the fields in record sections (spec 0287)', async () => {
    renderSheet()

    expect(await screen.findByRole('heading', { level: 3, name: label('absences.requests.form.sections.absence') })).toBeInTheDocument()
    expect(screen.getByRole('heading', { level: 3, name: label('absences.requests.form.sections.notes') })).toBeInTheDocument()
  })

  it('says why the type select is empty when no active type exists', async () => {
    typeOptionsMock.mockResolvedValue([])
    renderSheet()

    expect(await screen.findByText(label('absences.requests.form.typesEmpty'))).toBeInTheDocument()
  })

  it('does not show the empty-types hint when types exist', async () => {
    renderSheet()
    await pickType('Ferie')

    expect(screen.queryByText(label('absences.requests.form.typesEmpty'))).not.toBeInTheDocument()
  })

  it('shows the required errors with the accessible triad and sends nothing', async () => {
    renderSheet()
    fireEvent.click((await screen.findAllByRole('button', { name: label('absences.requests.form.send') }))[0])

    const typeError = await screen.findByText(label('absences.requests.form.typeRequired'))
    expect(typeError).toHaveAttribute('role', 'alert')
    const trigger = screen.getByRole('combobox', { name: new RegExp(label('absences.requests.form.type')) })
    expect(trigger).toHaveAttribute('aria-invalid', 'true')
    expect(trigger.getAttribute('aria-describedby')).toContain(typeError.id)
    expect(createMock).not.toHaveBeenCalled()
  })

  it('creates a full-day draft with the dates and the note', async () => {
    createMock.mockResolvedValue(buildRequest({ status: 'draft' }))
    renderSheet()
    await pickType('Ferie')
    fireEvent.change(dateField('absences.requests.form.startsOn'), { target: { value: '2026-11-02' } })
    fireEvent.change(dateField('absences.requests.form.endsOn'), { target: { value: '2026-11-04' } })
    fireEvent.change(screen.getByLabelText(label('absences.requests.form.note')), { target: { value: 'Mare' } })
    expect(await screen.findByText(label('absences.insight.balanceAfter'))).toBeInTheDocument()

    fireEvent.click(screen.getAllByRole('button', { name: label('absences.requests.form.saveDraft') })[0])

    await waitFor(() =>
      expect(createMock).toHaveBeenCalledWith({
        absence_type_id: 1,
        is_full_day: true,
        starts_on: '2026-11-02',
        ends_on: '2026-11-04',
        note: 'Mare',
        submit: false,
      }),
    )
    await waitFor(() => expect(onSaved).toHaveBeenCalled())
  })

  it('switches to an hourly request: one day, duration in minutes, submit and attachment', async () => {
    createMock.mockResolvedValue(buildRequest({ status: 'submitted' }))
    uploadMock.mockResolvedValue(buildRequest())
    renderSheet()
    await pickType('Ferie')

    fireEvent.click(modeRadio('absences.requests.form.modes.hours'))
    expect(screen.queryByLabelText(labelPattern('absences.requests.form.endsOn'))).not.toBeInTheDocument()
    fireEvent.change(dateField('absences.requests.form.date'), { target: { value: '2026-11-02' } })
    fireEvent.change(dateField('absences.requests.form.duration'), { target: { value: '02:30' } })
    await waitFor(() => expect(previewMock).toHaveBeenCalledWith(expect.objectContaining({ requested_minutes: 150 })))

    const file = new File(['x'], 'certificato.pdf', { type: 'application/pdf' })
    fireEvent.change(screen.getByLabelText(label('absences.requests.form.attachment')), { target: { files: [file] } })
    fireEvent.click(screen.getAllByRole('button', { name: label('absences.requests.form.send') })[0])

    await waitFor(() =>
      expect(createMock).toHaveBeenCalledWith({
        absence_type_id: 1,
        is_full_day: false,
        starts_on: '2026-11-02',
        ends_on: '2026-11-02',
        requested_minutes: 150,
        note: null,
        submit: true,
      }),
    )
    await waitFor(() => expect(uploadMock).toHaveBeenCalledWith(5, file))
  })

  it('sends a half day with its part and no duration field', async () => {
    createMock.mockResolvedValue(buildRequest({ status: 'draft' }))
    renderSheet()
    await pickType('Ferie')

    fireEvent.click(modeRadio('absences.requests.form.modes.half_day'))
    expect(screen.queryByLabelText(labelPattern('absences.requests.form.duration'))).not.toBeInTheDocument()
    fireEvent.click(screen.getByRole('radio', { name: label('absences.requests.form.dayParts.afternoon') }))
    fireEvent.change(dateField('absences.requests.form.date'), { target: { value: '2026-11-02' } })
    fireEvent.click(screen.getAllByRole('button', { name: label('absences.requests.form.saveDraft') })[0])

    await waitFor(() =>
      expect(createMock).toHaveBeenCalledWith({
        absence_type_id: 1,
        is_full_day: false,
        day_part: 'afternoon',
        starts_on: '2026-11-02',
        ends_on: '2026-11-02',
        note: null,
        submit: false,
      }),
    )
  })

  it('offers only the modes the type admits and moves to the first allowed on a type change', async () => {
    renderSheet()
    await pickType('Ferie')
    fireEvent.click(modeRadio('absences.requests.form.modes.hours'))
    expect(modeRadio('absences.requests.form.modes.hours')).toBeChecked()

    await pickType('Malattia')

    expect(screen.queryByRole('radio', { name: label('absences.requests.form.modes.hours') })).not.toBeInTheDocument()
    expect(screen.queryByRole('radio', { name: label('absences.requests.form.modes.half_day') })).not.toBeInTheDocument()
    expect(modeRadio('absences.requests.form.modes.full_day')).toBeChecked()
  })

  it('shows the person field only with manageAll and sends the chosen person', async () => {
    const { unmount } = renderSheet()
    await pickType('Ferie')
    expect(screen.queryByText(label('absences.requests.form.person'))).not.toBeInTheDocument()
    unmount()

    canMock.mockImplementation((permission) => permission === 'absence-requests.manageAll')
    createMock.mockResolvedValue(buildRequest({ status: 'draft' }))
    renderSheet()
    await pickType('Ferie')
    fireEvent.click(screen.getByRole('button', { name: label('absences.requests.form.person') }))
    fireEvent.change(dateField('absences.requests.form.startsOn'), { target: { value: '2026-11-02' } })
    fireEvent.change(dateField('absences.requests.form.endsOn'), { target: { value: '2026-11-04' } })
    fireEvent.click(screen.getAllByRole('button', { name: label('absences.requests.form.saveDraft') })[0])

    await waitFor(() => expect(createMock).toHaveBeenCalledWith(expect.objectContaining({ user_id: 9, submit: false })))
  })

  it('does not ask for the person when editing a draft', async () => {
    canMock.mockReturnValue(true)
    renderSheet(buildRequest({ status: 'draft', minutes: null }))

    expect(await screen.findByRole('dialog', { name: label('absences.requests.form.editTitle') })).toBeInTheDocument()
    expect(screen.queryByText(label('absences.requests.form.person'))).not.toBeInTheDocument()
  })

  it('previews the request live and sends the draft being edited as request_id', async () => {
    previewMock.mockResolvedValue(buildInsight({ minutes: 1560 }))
    renderSheet(buildRequest({ status: 'draft', minutes: null }))

    expect(await screen.findByText('3 g 2 h')).toBeInTheDocument()
    expect(previewMock).toHaveBeenCalledWith(expect.objectContaining({ request_id: 5, starts_on: '2026-11-02' }))
  })

  it('wires a 422 of the preview on its field with the accessible triad, without blocking the send', async () => {
    previewMock.mockRejectedValue(httpError(422, { message: 'Invalid', errors: { ends_on: ['Nessun giorno lavorativo nel periodo.'] } }))
    renderSheet()
    await pickType('Ferie')
    fireEvent.change(dateField('absences.requests.form.startsOn'), { target: { value: '2026-11-01' } })
    fireEvent.change(dateField('absences.requests.form.endsOn'), { target: { value: '2026-11-01' } })

    const alert = await screen.findByText('Nessun giorno lavorativo nel periodo.')
    expect(alert).toHaveAttribute('role', 'alert')
    const input = dateField('absences.requests.form.endsOn')
    expect(input).toHaveAttribute('aria-invalid', 'true')
    expect(input.getAttribute('aria-describedby')).toContain(alert.id)
    expect(screen.queryByText(label('absences.insight.error'))).not.toBeInTheDocument()
  })

  it('says a type that does not deduct has no balance', async () => {
    previewMock.mockResolvedValue(buildInsight({ balance: null }))
    renderSheet()
    await pickType('Malattia')
    fireEvent.change(dateField('absences.requests.form.startsOn'), { target: { value: '2026-11-02' } })
    fireEvent.change(dateField('absences.requests.form.endsOn'), { target: { value: '2026-11-02' } })
    expect(await screen.findByText(label('absences.insight.balanceNone'))).toBeInTheDocument()
  })

  it('wires a 422 on a field with the accessible triad', async () => {
    createMock.mockRejectedValue(
      httpError(422, { message: 'Invalid', errors: { starts_on: ['La richiesta si sovrappone a un\'altra.'] } }),
    )
    renderSheet()
    await pickType('Ferie')
    fireEvent.change(dateField('absences.requests.form.startsOn'), { target: { value: '2026-11-02' } })
    fireEvent.change(dateField('absences.requests.form.endsOn'), { target: { value: '2026-11-04' } })
    fireEvent.click(screen.getAllByRole('button', { name: label('absences.requests.form.send') })[0])

    const alert = await screen.findByText('La richiesta si sovrappone a un\'altra.')
    expect(alert).toHaveAttribute('role', 'alert')
    const input = dateField('absences.requests.form.startsOn')
    expect(input).toHaveAttribute('aria-invalid', 'true')
    expect(input.getAttribute('aria-describedby')).toContain(alert.id)
    expect(onSaved).not.toHaveBeenCalled()
  })

  it('updates an existing draft and sends it with the submit action', async () => {
    const draft = buildRequest({ status: 'draft', minutes: null })
    updateMock.mockResolvedValue(draft)
    submitMock.mockResolvedValue(buildRequest())
    renderSheet(draft)

    expect(await screen.findByRole('dialog', { name: label('absences.requests.form.editTitle') })).toBeInTheDocument()
    fireEvent.click(screen.getAllByRole('button', { name: label('absences.requests.form.send') })[0])

    await waitFor(() => expect(updateMock).toHaveBeenCalledWith(5, expect.objectContaining({ starts_on: '2026-11-02' })))
    await waitFor(() => expect(submitMock).toHaveBeenCalledWith(5))
    expect(createMock).not.toHaveBeenCalled()
  })
})
