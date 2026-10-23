import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { CommissionConfigurationDetailView } from './commission-configuration-detail'
import { configurationFixture, configurationWrapper } from './commission-configuration-test-fixtures'

/** Spec 0308 AC-013: the question asked after a rule row is saved. */

const updateMock = vi.fn()
const impactMock = vi.fn()
const recalculateMock = vi.fn()
const toastSuccessMock = vi.fn()

vi.mock('./api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('./api')>()),
  updateCommissionConfiguration: (...args: unknown[]) => updateMock(...args),
  fetchCommissionConfigurationQuoteImpact: (...args: unknown[]) => impactMock(...args),
  recalculateConfigurationQuotes: (...args: unknown[]) => recalculateMock(...args),
}))

vi.mock('sonner', () => ({
  toast: { success: (...args: unknown[]) => toastSuccessMock(...args), error: vi.fn() },
}))

vi.mock('@/components/form/relation-select-field', async () => ({
  RelationSelectField: (await import('./commission-configuration-relation-double')).RelationSelectFieldDouble,
}))
vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'modal' }))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: () => <div>activity log</div>,
}))

const IMPACT = { quotes_count: 3, lines_count: 5, manual_commissions_count: 2, can_apply: true }

const saveField = (label: string, input: () => HTMLElement, value: string) => {
  fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: label }) }))
  fireEvent.change(input(), { target: { value } })
  fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))
}
const saveValue = () => saveField('Commission value', () => screen.getByLabelText(/^Commission value/), '8.5')
const saveName = () => saveField('Configuration name', () => screen.getByRole('textbox'), 'Renamed')

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.clearAllMocks()
  updateMock.mockResolvedValue(configurationFixture())
  impactMock.mockResolvedValue(IMPACT)
  recalculateMock.mockResolvedValue({ quotes_count: 3 })
})

const renderDetail = () =>
  render(<CommissionConfigurationDetailView configuration={configurationFixture()} />, {
    wrapper: configurationWrapper(),
  })

describe('rule propagation dialog', () => {
  it('asks a non-dismissible question with the counts after a non-name save', async () => {
    renderDetail()
    saveValue()

    const dialog = await screen.findByRole('dialog')
    expect(impactMock).toHaveBeenCalledWith(1)
    expect(dialog).toHaveTextContent('3 open quotes, 5 lines, 2 of them commissions edited by hand.')
    expect(screen.getAllByRole('radio')).toHaveLength(3)
    screen.getAllByRole('radio').forEach((radio) => expect(radio).not.toBeChecked())
    expect(screen.getByRole('button', { name: 'Confirm' })).toBeDisabled()
    expect(screen.queryByRole('button', { name: /Cancel|Close/ })).not.toBeInTheDocument()
    fireEvent.keyDown(dialog, { key: 'Escape' })
    expect(screen.getByRole('dialog')).toBeInTheDocument()
  })

  it('NONE closes without calling the server', async () => {
    renderDetail()
    saveValue()
    await screen.findByRole('dialog')

    fireEvent.click(screen.getByRole('radio', { name: /Only the new quote lines/ }))
    fireEvent.click(screen.getByRole('button', { name: 'Confirm' }))

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
    expect(recalculateMock).not.toHaveBeenCalled()
  })

  it('KEEP_MANUAL queues the recalculation and toasts that it started', async () => {
    renderDetail()
    saveValue()
    await screen.findByRole('dialog')

    fireEvent.click(screen.getByRole('radio', { name: /keep the 2 manual ones/ }))
    fireEvent.click(screen.getByRole('button', { name: 'Confirm' }))

    await waitFor(() => expect(recalculateMock).toHaveBeenCalledWith(1, 'KEEP_MANUAL'))
    await waitFor(() => expect(toastSuccessMock).toHaveBeenCalledWith(expect.stringContaining('Recalculation started')))
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
  })

  it('REGENERATE_ALL sends that mode', async () => {
    renderDetail()
    saveValue()
    await screen.findByRole('dialog')

    fireEvent.click(screen.getByRole('radio', { name: /Regenerate all/ }))
    fireEvent.click(screen.getByRole('button', { name: 'Confirm' }))

    await waitFor(() => expect(recalculateMock).toHaveBeenCalledWith(1, 'REGENERATE_ALL'))
  })

  it('does not ask after saving the name', async () => {
    renderDetail()
    saveName()

    await waitFor(() => expect(updateMock).toHaveBeenCalled())
    expect(impactMock).not.toHaveBeenCalled()
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  it.each([
    { quotes_count: 0, can_apply: true },
    { quotes_count: 3, can_apply: false },
  ])('does not ask when the impact is %j', async (override) => {
    impactMock.mockResolvedValue({ ...IMPACT, ...override })
    renderDetail()
    saveValue()

    await waitFor(() => expect(impactMock).toHaveBeenCalled())
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })
})
