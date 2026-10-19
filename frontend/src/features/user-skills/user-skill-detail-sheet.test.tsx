import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { UserSkillDetailSheet } from '@/features/user-skills/user-skill-detail-sheet'
import { buildUserSkill, userSkillWrapper } from '@/features/user-skills/user-skill-fixtures'
import type { UserSkill } from '@/features/user-skills/types'

const fetchMock = vi.fn()
const deleteMock = vi.fn()
vi.mock('@/features/user-skills/api', () => ({
  fetchUserSkill: (id: number) => fetchMock(id),
  deleteUserSkill: (id: number) => deleteMock(id),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const label = (key: string) => i18n.t(key)
const onClose = vi.fn()
const onEdit = vi.fn()
const refreshGrid = vi.fn()

function renderDetail(userSkill: UserSkill) {
  fetchMock.mockResolvedValue(userSkill)
  return render(
    <UserSkillDetailSheet userSkillId={userSkill.id} onClose={onClose} onEdit={onEdit} refreshGrid={refreshGrid} />,
    { wrapper: userSkillWrapper() },
  )
}

const button = (key: string) => screen.queryByRole('button', { name: label(key) })

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  ;[fetchMock, deleteMock, onClose, onEdit, refreshGrid].forEach((mock) => mock.mockReset())
})

describe('UserSkillDetailSheet', () => {
  it('shows data and the level with its number and name', async () => {
    renderDetail(buildUserSkill({ notes: 'Certificato B2' }))

    expect(await screen.findByText('Anna Rossi')).toBeInTheDocument()
    expect(screen.getByText('Inglese')).toBeInTheDocument()
    expect(screen.getByText(`3 · ${label('skills.levels.3')}`)).toBeInTheDocument()
    expect(screen.getByText('10/03/2026')).toBeInTheDocument()
    expect(screen.getByText('Certificato B2')).toBeInTheDocument()
  })

  it('hides edit and delete when the server says the row is not editable', async () => {
    renderDetail(buildUserSkill({ can_edit: false }))
    await screen.findByText('Anna Rossi')

    expect(button('userSkills.actions.edit')).not.toBeInTheDocument()
    expect(button('userSkills.actions.delete')).not.toBeInTheDocument()
  })

  it('opens the edit form from the detail when editable', async () => {
    const userSkill = buildUserSkill()
    renderDetail(userSkill)
    await screen.findByText('Anna Rossi')

    fireEvent.click(button('userSkills.actions.edit') as HTMLElement)
    expect(onEdit).toHaveBeenCalledWith(userSkill)
  })

  it('deletes only after the confirmation, then closes and refreshes the grid', async () => {
    deleteMock.mockResolvedValue(undefined)
    renderDetail(buildUserSkill())
    await screen.findByText('Anna Rossi')

    fireEvent.click(button('userSkills.actions.delete') as HTMLElement)
    expect(deleteMock).not.toHaveBeenCalled()
    const dialog = await screen.findByRole('alertdialog')
    fireEvent.click(
      Array.from(dialog.querySelectorAll('button')).find((b) => b.textContent === label('userSkills.actions.delete')) as HTMLElement,
    )

    await waitFor(() => expect(deleteMock).toHaveBeenCalledWith(5))
    await waitFor(() => expect(onClose).toHaveBeenCalled())
    expect(refreshGrid).toHaveBeenCalled()
  })

  it('shows the not-found message when the row is out of visibility', async () => {
    fetchMock.mockRejectedValue(Object.assign(new Error('nf'), { isAxiosError: true, response: { status: 404 } }))
    render(<UserSkillDetailSheet userSkillId={9} onClose={onClose} onEdit={onEdit} />, { wrapper: userSkillWrapper() })

    expect(await screen.findByRole('alert')).toBeInTheDocument()
  })
})
