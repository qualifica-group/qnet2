import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { StaffTrainingCompliance } from '@/features/staff-trainings/staff-training-compliance'
import { COMPLIANCE, trainingWrapper } from '@/features/staff-trainings/staff-training-fixtures'

const fetchMock = vi.fn()
vi.mock('@/features/staff-trainings/api', () => ({ fetchCompliance: (params: unknown) => fetchMock(params) }))
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
  fetchMock.mockResolvedValue(COMPLIANCE)
  onOpen.mockReset()
})

describe('StaffTrainingCompliance', () => {
  it('shows every status with its text, not only colour', async () => {
    render(<StaffTrainingCompliance onOpen={onOpen} />, { wrapper: trainingWrapper() })

    const table = await screen.findByRole('table', { name: label('staffTrainings.compliance.caption') })
    for (const status of ['valid', 'expiring', 'expired', 'missing']) {
      expect(table).toHaveTextContent(label(`staffTrainings.compliance.status.${status}`))
    }
    expect(fetchMock).toHaveBeenCalledWith({})
  })

  it('explains the expiring window in the legend with the server days', async () => {
    render(<StaffTrainingCompliance onOpen={onOpen} />, { wrapper: trainingWrapper() })

    expect(await screen.findByText('In scadenza entro 60 giorni')).toBeInTheDocument()
  })

  it('opens the training only for rows backed by one', async () => {
    render(<StaffTrainingCompliance onOpen={onOpen} />, { wrapper: trainingWrapper() })
    await screen.findByRole('table')

    fireEvent.click(screen.getByRole('button', { name: label('staffTrainings.compliance.openTraining', { person: 'Carlo Bianchi', course: 'Sicurezza generale' }) }))
    expect(onOpen).toHaveBeenCalledWith(7)
    expect(screen.getAllByRole('button', { name: /Apri la formazione/ })).toHaveLength(3)
  })

  it('filters by status, function and course through the query', async () => {
    render(<StaffTrainingCompliance onOpen={onOpen} />, { wrapper: trainingWrapper() })
    await screen.findByRole('table')

    fireEvent.click(screen.getByRole('tab', { name: label('staffTrainings.compliance.status.expired') }))
    await waitFor(() => expect(fetchMock).toHaveBeenLastCalledWith({ status: 'expired' }))

    fireEvent.change(screen.getByRole('combobox', { name: label('staffTrainings.compliance.functionFilter') }), { target: { value: '7' } })
    await waitFor(() => expect(fetchMock).toHaveBeenLastCalledWith({ status: 'expired', business_function_id: 7 }))

    fireEvent.change(screen.getByRole('combobox', { name: label('staffTrainings.compliance.courseFilter') }), { target: { value: '3' } })
    await waitFor(() =>
      expect(fetchMock).toHaveBeenLastCalledWith({ status: 'expired', business_function_id: 7, staff_course_id: 3 }),
    )

    fireEvent.click(screen.getByRole('tab', { name: label('staffTrainings.compliance.allStatuses') }))
    await waitFor(() => expect(fetchMock).toHaveBeenLastCalledWith({ business_function_id: 7, staff_course_id: 3 }))
  })

  it('says so when nothing matches and when the load fails', async () => {
    fetchMock.mockResolvedValueOnce({ expiring_days: 60, rows: [] })
    const { unmount } = render(<StaffTrainingCompliance onOpen={onOpen} />, { wrapper: trainingWrapper() })
    expect(await screen.findByText(label('staffTrainings.compliance.empty'))).toBeInTheDocument()
    unmount()

    fetchMock.mockRejectedValue(new Error('boom'))
    render(<StaffTrainingCompliance onOpen={onOpen} />, { wrapper: trainingWrapper() })
    expect(await screen.findByRole('alert')).toHaveTextContent(label('staffTrainings.compliance.error'))
  })
})
