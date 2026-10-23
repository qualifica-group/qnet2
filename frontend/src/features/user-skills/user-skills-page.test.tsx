import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import UserSkillsPage from '@/features/user-skills/user-skills-page'
import { buildUserSkill, userSkillWrapper } from '@/features/user-skills/user-skill-fixtures'

const permissions = new Set<string>()
let hasSubordinates = false
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => permissions.has(permission), hasRole: () => false, roles: [], hasSubordinates, isLoading: false }),
}))
vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: React.ReactNode }) => <header>{actions}</header>,
}))
vi.mock('@/features/user-skills/user-skills-grid', () => ({
  UserSkillsGrid: ({ onOpen }: { onOpen: (id: number) => void }) => (
    <button type="button" onClick={() => onOpen(5)}>
      open-5
    </button>
  ),
}))
vi.mock('@/features/user-skills/user-skill-coverage', () => ({
  UserSkillCoverage: ({ onOpen }: { onOpen: (id: number) => void }) => (
    <button type="button" onClick={() => onOpen(5)}>
      coverage-open-5
    </button>
  ),
}))
vi.mock('@/features/user-skills/api', () => ({
  fetchUserSkill: () => Promise.resolve(buildUserSkill()),
}))
vi.mock('@/components/ui/async-paginated-select', async () => ({
  AsyncPaginatedSelect: (await import('@/features/staff-trainings/picker-stub')).PickerStub,
}))

const label = (key: string) => i18n.t(key)

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  hasSubordinates = false
  permissions.clear()
  permissions.add('user-skills.viewAny')
})

describe('UserSkillsPage', () => {
  it('opens with subordinates alone and hides "Assign skill" (spec 0309 D-3)', () => {
    permissions.clear()
    hasSubordinates = true
    render(<UserSkillsPage />, { wrapper: userSkillWrapper() })

    expect(screen.getByRole('button', { name: 'open-5' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: label('userSkills.page.assign') })).not.toBeInTheDocument()
  })

  it('shows a forbidden message without viewAny nor subordinates', () => {
    permissions.clear()
    render(<UserSkillsPage />, { wrapper: userSkillWrapper() })

    expect(screen.getByText(label('userSkills.forbidden'))).toBeInTheDocument()
  })

  it('starts on the people tab and hides "Assign skill" for a plain viewer', () => {
    render(<UserSkillsPage />, { wrapper: userSkillWrapper() })

    expect(screen.getByRole('tab', { name: label('userSkills.page.views.people'), selected: true })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'open-5' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: label('userSkills.page.assign') })).not.toBeInTheDocument()
  })

  it('hides "Assign skill" with view-all only, which is read-only', () => {
    permissions.add('user-skills.viewAll')
    render(<UserSkillsPage />, { wrapper: userSkillWrapper() })

    expect(screen.queryByRole('button', { name: label('userSkills.page.assign') })).not.toBeInTheDocument()
  })

  it('shows "Assign skill" with manage and opens the form', async () => {
    permissions.add('user-skills.manage')
    render(<UserSkillsPage />, { wrapper: userSkillWrapper() })

    fireEvent.click(screen.getByRole('button', { name: label('userSkills.page.assign') }))
    expect(await screen.findByRole('dialog', { name: label('userSkills.form.createTitle') })).toBeInTheDocument()
  })

  it('switches to the coverage tab', () => {
    render(<UserSkillsPage />, { wrapper: userSkillWrapper() })

    fireEvent.click(screen.getByRole('tab', { name: label('userSkills.page.views.coverage') }))
    expect(screen.getByRole('button', { name: 'coverage-open-5' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'open-5' })).not.toBeInTheDocument()
  })

  it('opens the detail from a grid row', async () => {
    render(<UserSkillsPage />, { wrapper: userSkillWrapper() })

    fireEvent.click(screen.getByRole('button', { name: 'open-5' }))
    expect(await screen.findByText('Anna Rossi')).toBeInTheDocument()
  })
})
