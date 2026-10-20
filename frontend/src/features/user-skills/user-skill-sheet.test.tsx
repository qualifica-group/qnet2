import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { UserSkillSheet } from '@/features/user-skills/user-skill-sheet'
import { buildUserSkill, httpError, userSkillWrapper } from '@/features/user-skills/user-skill-fixtures'

const createMock = vi.fn()
const updateMock = vi.fn()
vi.mock('@/features/user-skills/api', () => ({
  createUserSkill: (payload: unknown) => createMock(payload),
  updateUserSkill: (id: number, payload: unknown) => updateMock(id, payload),
}))
vi.mock('@/components/ui/async-paginated-select', async () => ({
  AsyncPaginatedSelect: (await import('@/features/staff-trainings/picker-stub')).PickerStub,
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const label = (key: string) => i18n.t(key)
const onSaved = vi.fn()

function renderSheet(userSkill?: ReturnType<typeof buildUserSkill>) {
  return render(<UserSkillSheet open onOpenChange={vi.fn()} userSkill={userSkill} onSaved={onSaved} />, {
    wrapper: userSkillWrapper(),
  })
}

/** Anchored: a required label also renders a trailing "*". */
const labelPattern = (key: string) => new RegExp(`^${label(key)}\\s*\\*?$`)
const save = () => fireEvent.click(screen.getByRole('button', { name: label('userSkills.form.save') }))

async function pickLevel(level: number) {
  fireEvent.keyDown(screen.getByRole('combobox', { name: labelPattern('userSkills.form.level') }), { key: 'ArrowDown' })
  fireEvent.click(await screen.findByRole('option', { name: `${level} · ${label(`skills.levels.${level}`)}` }))
}

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  createMock.mockReset()
  updateMock.mockReset()
  onSaved.mockReset()
})

describe('UserSkillSheet', () => {
  it('shows the required errors with the accessible triad and sends nothing', async () => {
    renderSheet()
    save()

    const alert = await screen.findByText(label('userSkills.form.userRequired'))
    expect(alert).toHaveAttribute('role', 'alert')
    const person = screen.getByRole('combobox', { name: label('userSkills.form.user') })
    expect(person).toHaveAttribute('aria-invalid', 'true')
    expect(person.getAttribute('aria-describedby')).toContain(alert.id)
    expect(screen.getByText(label('userSkills.form.skillRequired'))).toBeInTheDocument()
    expect(screen.getByText(label('userSkills.form.levelRequired'))).toBeInTheDocument()
    expect(screen.getByText(label('userSkills.form.dateRequired'))).toBeInTheDocument()
    expect(createMock).not.toHaveBeenCalled()
  })

  it('refuses an assessment date in the future', async () => {
    renderSheet()
    fireEvent.change(screen.getByLabelText(labelPattern('userSkills.form.assessedOn')), { target: { value: '2999-01-01' } })
    save()

    expect(await screen.findByText(label('userSkills.form.dateInFuture'))).toBeInTheDocument()
    expect(createMock).not.toHaveBeenCalled()
  })

  it('assigns with the picked person, skill and level and closes on success', async () => {
    createMock.mockResolvedValue(buildUserSkill())
    renderSheet()
    fireEvent.change(screen.getByRole('combobox', { name: label('userSkills.form.user') }), { target: { value: '7' } })
    fireEvent.change(screen.getByRole('combobox', { name: label('userSkills.form.skill') }), { target: { value: '3' } })
    await pickLevel(4)
    fireEvent.change(screen.getByLabelText(labelPattern('userSkills.form.assessedOn')), { target: { value: '2026-03-10' } })
    fireEvent.change(screen.getByLabelText(label('userSkills.form.notes')), { target: { value: ' Madrelingua ' } })
    save()

    await waitFor(() =>
      expect(createMock).toHaveBeenCalledWith({
        user_id: 7,
        skill_id: 3,
        level: 4,
        assessed_on: '2026-03-10',
        notes: 'Madrelingua',
      }),
    )
    await waitFor(() => expect(onSaved).toHaveBeenCalled())
  })

  it('edits a saved row with a PATCH and the stored values prefilled', async () => {
    updateMock.mockResolvedValue(buildUserSkill())
    renderSheet(buildUserSkill({ notes: 'Prima' }))

    expect(await screen.findByText(label('userSkills.form.editTitle'))).toBeInTheDocument()
    expect(screen.getByLabelText(label('userSkills.form.notes'))).toHaveValue('Prima')
    save()

    await waitFor(() =>
      expect(updateMock).toHaveBeenCalledWith(5, {
        user_id: 7,
        skill_id: 3,
        level: 3,
        assessed_on: '2026-03-10',
        notes: 'Prima',
      }),
    )
    expect(createMock).not.toHaveBeenCalled()
  })

  it('shows the 422 of an already assigned pair on the skill field with the accessible triad', async () => {
    updateMock.mockRejectedValue(
      httpError(422, { message: 'Invalid', errors: { skill_id: ['La persona ha già questa competenza.'] } }),
    )
    renderSheet(buildUserSkill())
    save()

    const alert = await screen.findByText('La persona ha già questa competenza.')
    expect(alert).toHaveAttribute('role', 'alert')
    const skill = screen.getByRole('combobox', { name: label('userSkills.form.skill') })
    expect(skill).toHaveAttribute('aria-invalid', 'true')
    expect(skill.getAttribute('aria-describedby')).toContain(alert.id)
    expect(onSaved).not.toHaveBeenCalled()
  })
})
