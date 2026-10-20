import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { UserSkillCoverage } from '@/features/user-skills/user-skill-coverage'
import { COVERAGE, userSkillWrapper } from '@/features/user-skills/user-skill-fixtures'

const fetchMock = vi.fn()
vi.mock('@/features/user-skills/api', () => ({ fetchCoverage: (params: unknown) => fetchMock(params) }))
vi.mock('@/components/ui/async-paginated-select', async () => ({
  AsyncPaginatedSelect: (await import('@/features/staff-trainings/picker-stub')).PickerStub,
}))

const label = (key: string, options?: Record<string, string>) => i18n.t(key, options)
const onOpen = vi.fn()

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  fetchMock.mockReset()
  fetchMock.mockResolvedValue(COVERAGE)
  onOpen.mockReset()
})

describe('UserSkillCoverage', () => {
  it('shows required and current levels and every status with its text, not only colour', async () => {
    render(<UserSkillCoverage onOpen={onOpen} />, { wrapper: userSkillWrapper() })

    const table = await screen.findByRole('table', { name: label('userSkills.coverage.caption') })
    for (const status of ['covered', 'below', 'missing']) {
      expect(table).toHaveTextContent(label(`userSkills.coverage.status.${status}`))
    }
    expect(table).toHaveTextContent(`4 · ${label('skills.levels.4')}`)
    expect(table).toHaveTextContent(`2 · ${label('skills.levels.2')}`)
    expect(fetchMock).toHaveBeenCalledWith({})
  })

  it('opens the person skill only for rows backed by one', async () => {
    render(<UserSkillCoverage onOpen={onOpen} />, { wrapper: userSkillWrapper() })
    await screen.findByRole('table')

    fireEvent.click(screen.getByRole('button', { name: label('userSkills.coverage.openSkill', { person: 'Anna Rossi', skill: 'Excel' }) }))
    expect(onOpen).toHaveBeenCalledWith(6)
    expect(screen.getAllByRole('button', { name: /Apri la competenza/ })).toHaveLength(2)
  })

  it('filters by status, function and skill through the query', async () => {
    render(<UserSkillCoverage onOpen={onOpen} />, { wrapper: userSkillWrapper() })
    await screen.findByRole('table')

    fireEvent.click(screen.getByRole('tab', { name: label('userSkills.coverage.status.missing') }))
    await waitFor(() => expect(fetchMock).toHaveBeenLastCalledWith({ status: 'missing' }))

    fireEvent.change(screen.getByRole('combobox', { name: label('userSkills.coverage.functionFilter') }), { target: { value: '7' } })
    await waitFor(() => expect(fetchMock).toHaveBeenLastCalledWith({ status: 'missing', business_function_id: 7 }))

    fireEvent.change(screen.getByRole('combobox', { name: label('userSkills.coverage.skillFilter') }), { target: { value: '3' } })
    await waitFor(() =>
      expect(fetchMock).toHaveBeenLastCalledWith({ status: 'missing', business_function_id: 7, skill_id: 3 }),
    )

    fireEvent.click(screen.getByRole('tab', { name: label('userSkills.coverage.allStatuses') }))
    await waitFor(() => expect(fetchMock).toHaveBeenLastCalledWith({ business_function_id: 7, skill_id: 3 }))
  })

  it('says so when nothing matches and when the load fails', async () => {
    fetchMock.mockResolvedValueOnce({ rows: [] })
    const { unmount } = render(<UserSkillCoverage onOpen={onOpen} />, { wrapper: userSkillWrapper() })
    expect(await screen.findByText(label('userSkills.coverage.empty'))).toBeInTheDocument()
    unmount()

    fetchMock.mockRejectedValue(new Error('boom'))
    render(<UserSkillCoverage onOpen={onOpen} />, { wrapper: userSkillWrapper() })
    expect(await screen.findByRole('alert')).toHaveTextContent(label('userSkills.coverage.error'))
  })
})
