import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { StaffTrainingSheet } from '@/features/staff-trainings/staff-training-sheet'
import {
  buildTraining,
  httpError,
  trainingWrapper,
} from '@/features/staff-trainings/staff-training-fixtures'

const createMock = vi.fn()
const updateMock = vi.fn()
vi.mock('@/features/staff-trainings/api', () => ({
  createStaffTraining: (payload: unknown) => createMock(payload),
  updateStaffTraining: (id: number, payload: unknown) => updateMock(id, payload),
}))
vi.mock('@/components/ui/async-paginated-select', async () => ({
  AsyncPaginatedSelect: (await import('@/features/staff-trainings/picker-stub')).PickerStub,
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const label = (key: string) => i18n.t(key)
const onSaved = vi.fn()

function renderSheet(training?: ReturnType<typeof buildTraining>) {
  return render(<StaffTrainingSheet open onOpenChange={vi.fn()} training={training} onSaved={onSaved} />, {
    wrapper: trainingWrapper(),
  })
}

/** Anchored: a required label also renders a trailing "*". */
const labelPattern = (key: string) => new RegExp(`^${label(key)}\\s*\\*?$`)
const save = () => fireEvent.click(screen.getAllByRole('button', { name: label('staffTrainings.form.save') })[0])

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  createMock.mockReset()
  updateMock.mockReset()
  onSaved.mockReset()
})

describe('StaffTrainingSheet', () => {
  it('groups the fields in record sections (spec 0287)', () => {
    renderSheet()

    expect(screen.getByRole('heading', { level: 3, name: label('staffTrainings.form.sections.personCourse') })).toBeInTheDocument()
    expect(screen.getByRole('heading', { level: 3, name: label('staffTrainings.form.sections.outcome') })).toBeInTheDocument()
  })

  it('shows the required errors with the accessible triad and sends nothing', async () => {
    renderSheet()
    save()

    const alert = await screen.findByText(label('staffTrainings.form.userRequired'))
    expect(alert).toHaveAttribute('role', 'alert')
    const person = screen.getByRole('combobox', { name: label('staffTrainings.form.user') })
    expect(person).toHaveAttribute('aria-invalid', 'true')
    expect(person.getAttribute('aria-describedby')).toContain(alert.id)
    expect(screen.getByText(label('staffTrainings.form.courseRequired'))).toBeInTheDocument()
    expect(screen.getByText(label('staffTrainings.form.dateRequired'))).toBeInTheDocument()
    expect(createMock).not.toHaveBeenCalled()
  })

  it('refuses a completion date in the future', async () => {
    renderSheet()
    fireEvent.change(screen.getByLabelText(labelPattern('staffTrainings.form.completedOn')), {
      target: { value: '2999-01-01' },
    })
    save()

    expect(await screen.findByText(label('staffTrainings.form.dateInFuture'))).toBeInTheDocument()
    expect(createMock).not.toHaveBeenCalled()
  })

  it('creates with the picked person and course and closes on success', async () => {
    createMock.mockResolvedValue(buildTraining())
    renderSheet()
    fireEvent.change(screen.getByRole('combobox', { name: label('staffTrainings.form.user') }), { target: { value: '7' } })
    fireEvent.change(screen.getByRole('combobox', { name: label('staffTrainings.form.course') }), { target: { value: '3' } })
    fireEvent.change(screen.getByLabelText(labelPattern('staffTrainings.form.completedOn')), {
      target: { value: '2026-03-10' },
    })
    fireEvent.change(screen.getByLabelText(label('staffTrainings.form.notes')), { target: { value: ' Ottimo ' } })
    save()

    await waitFor(() =>
      expect(createMock).toHaveBeenCalledWith({
        user_id: 7,
        staff_course_id: 3,
        completed_on: '2026-03-10',
        outcome: 'passed',
        notes: 'Ottimo',
      }),
    )
    await waitFor(() => expect(onSaved).toHaveBeenCalled())
  })

  it('edits a saved training with a PATCH and the stored values prefilled', async () => {
    updateMock.mockResolvedValue(buildTraining())
    renderSheet(buildTraining({ notes: 'Prima' }))

    expect(await screen.findByRole('dialog', { name: label('staffTrainings.form.editTitle') })).toBeInTheDocument()
    expect(screen.getByLabelText(label('staffTrainings.form.notes'))).toHaveValue('Prima')
    save()

    await waitFor(() =>
      expect(updateMock).toHaveBeenCalledWith(5, {
        user_id: 7,
        staff_course_id: 3,
        completed_on: '2026-03-10',
        outcome: 'passed',
        notes: 'Prima',
      }),
    )
    expect(createMock).not.toHaveBeenCalled()
  })

  it('wires a server 422 onto the field with the accessible triad', async () => {
    updateMock.mockRejectedValue(
      httpError(422, { message: 'Invalid', errors: { completed_on: ['Data non valida.'] } }),
    )
    renderSheet(buildTraining())
    save()

    const alert = await screen.findByText('Data non valida.')
    expect(alert).toHaveAttribute('role', 'alert')
    const input = screen.getByLabelText(labelPattern('staffTrainings.form.completedOn'))
    expect(input).toHaveAttribute('aria-invalid', 'true')
    expect(input.getAttribute('aria-describedby')).toContain(alert.id)
    expect(onSaved).not.toHaveBeenCalled()
  })
})
