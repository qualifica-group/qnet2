import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import StaffTrainingsPage from '@/features/staff-trainings/staff-trainings-page'
import { buildTraining, trainingWrapper } from '@/features/staff-trainings/staff-training-fixtures'

const permissions = new Set<string>()
let hasSubordinates = false
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => permissions.has(permission), hasRole: () => false, roles: [], hasSubordinates, isLoading: false }),
}))
vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: React.ReactNode }) => <header>{actions}</header>,
}))
vi.mock('@/features/staff-trainings/staff-training-register', () => ({
  StaffTrainingRegister: ({ onOpen }: { onOpen: (id: number) => void }) => (
    <button type="button" onClick={() => onOpen(5)}>
      open-5
    </button>
  ),
}))
vi.mock('@/features/staff-trainings/staff-training-compliance', () => ({
  StaffTrainingCompliance: ({ onOpen }: { onOpen: (id: number) => void }) => (
    <button type="button" onClick={() => onOpen(5)}>
      compliance-open-5
    </button>
  ),
}))
vi.mock('@/features/staff-trainings/api', () => ({
  fetchStaffTraining: () => Promise.resolve(buildTraining()),
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
  permissions.add('staff-trainings.viewAny')
})

describe('StaffTrainingsPage', () => {
  it('opens with subordinates alone, read-only (spec 0309)', () => {
    permissions.clear()
    hasSubordinates = true
    render(<StaffTrainingsPage />, { wrapper: trainingWrapper() })

    expect(screen.getByRole('button', { name: 'open-5' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: label('staffTrainings.page.register') })).not.toBeInTheDocument()
  })

  it('shows a forbidden message without viewAny nor subordinates', () => {
    permissions.clear()
    render(<StaffTrainingsPage />, { wrapper: trainingWrapper() })

    expect(screen.getByText(label('staffTrainings.forbidden'))).toBeInTheDocument()
  })

  it('starts on the register and hides "Record training" without manage', () => {
    render(<StaffTrainingsPage />, { wrapper: trainingWrapper() })

    expect(screen.getByRole('tab', { name: label('staffTrainings.page.views.register'), selected: true })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'open-5' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: label('staffTrainings.page.register') })).not.toBeInTheDocument()
  })

  it('switches to the compliance tab', () => {
    render(<StaffTrainingsPage />, { wrapper: trainingWrapper() })

    fireEvent.click(screen.getByRole('tab', { name: label('staffTrainings.page.views.compliance') }))
    expect(screen.getByRole('button', { name: 'compliance-open-5' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'open-5' })).not.toBeInTheDocument()
  })

  it('opens the register form from "Record training" with manage', async () => {
    permissions.add('staff-trainings.manage')
    render(<StaffTrainingsPage />, { wrapper: trainingWrapper() })

    fireEvent.click(screen.getByRole('button', { name: label('staffTrainings.page.register') }))
    expect(await screen.findByRole('dialog', { name: label('staffTrainings.form.createTitle') })).toBeInTheDocument()
  })

  it('opens the detail from a register row', async () => {
    render(<StaffTrainingsPage />, { wrapper: trainingWrapper() })

    fireEvent.click(screen.getByRole('button', { name: 'open-5' }))
    expect(await screen.findByText('Anna Rossi')).toBeInTheDocument()
  })
})
