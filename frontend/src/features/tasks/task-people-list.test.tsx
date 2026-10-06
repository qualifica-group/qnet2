import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { UserDetailSheetContext } from '@/features/users/user-detail-sheet-context'
import { TaskPeopleList } from '@/features/tasks/task-people-list'

/** User directive 2026-10-06 ("regola generale"): many people collapse into the avatar stack with "+N". */

function people(count: number) {
  return Array.from({ length: count }, (_, index) => ({ id: index + 1, name: `Persona ${index + 1}` }))
}

function renderList(count: number) {
  return render(
    <UserDetailSheetContext.Provider value={{ openUserDetail: vi.fn(), canOpenUserDetail: true }}>
      <TaskPeopleList people={people(count)} />
    </UserDetailSheetContext.Provider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('TaskPeopleList', () => {
  it('names a few people one by one', () => {
    renderList(3)

    expect(screen.getByText('Persona 1')).toBeInTheDocument()
    expect(screen.getByText('Persona 3')).toBeInTheDocument()
    expect(screen.queryByText(/^\+\d+$/)).not.toBeInTheDocument()
  })

  it('collapses many people into the avatar stack, the rest behind "+N"', () => {
    renderList(8)

    expect(screen.queryByText('Persona 1')).not.toBeInTheDocument()
    expect(screen.getByText('+3')).toHaveAccessibleName('Persona 6, Persona 7, Persona 8')
  })
})
