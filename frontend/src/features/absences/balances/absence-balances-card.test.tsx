import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import '@/i18n'
import { AbsenceBalancesCard } from '@/features/absences/balances/absence-balances-card'
import type { AbsenceBalances } from '@/features/absences/types'

const fetchBalancesMock = vi.fn()
const saveAllowanceMock = vi.fn()
vi.mock('@/features/absences/api', () => ({
  fetchAbsenceBalances: (params: unknown) => fetchBalancesMock(params),
  saveAbsenceAllowance: (payload: unknown) => saveAllowanceMock(payload),
}))

const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: canMock, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: () => <button type="button">picker</button>,
}))

const BALANCES: AbsenceBalances = {
  user: { id: 7, name: 'Anna Rossi' },
  year: new Date().getFullYear(),
  daily_minutes: 480,
  balances: [
    {
      absence_type: { id: 1, name: 'Ferie', color: 'slate' },
      source: 'allowance',
      annual_minutes: 9600,
      carried_over_minutes: 450,
      accrued_minutes: 5000,
      used_minutes: 600,
      pending_minutes: 120,
      residual_minutes: 4280,
    },
    {
      absence_type: { id: 2, name: 'Permessi', color: 'slate' },
      source: 'allowance',
      annual_minutes: 0,
      carried_over_minutes: 0,
      accrued_minutes: 0,
      used_minutes: 90,
      pending_minutes: 0,
      residual_minutes: -90,
    },
  ],
}

function httpError(status: number, data: object): AxiosError {
  return Object.assign(new AxiosError('request failed'), { response: { status, data } })
}

function renderCard() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <AbsenceBalancesCard userId={7} />
    </QueryClientProvider>,
  )
}

describe('AbsenceBalancesCard', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    canMock.mockReturnValue(false)
    fetchBalancesMock.mockResolvedValue(BALANCES)
  })

  it('renders a row per type with hours and flags a negative residual', async () => {
    renderCard()

    expect(await screen.findByText('Ferie')).toBeInTheDocument()
    expect(screen.getByText('160h 00m')).toBeInTheDocument()
    expect(screen.getByText('7h 30m')).toBeInTheDocument()
    expect(screen.getByText('-1h 30m')).toBeInTheDocument()
    expect(screen.getByText('Residuo negativo')).toBeInTheDocument()
  })

  it('shows the empty state when no type deducts a balance', async () => {
    fetchBalancesMock.mockResolvedValue({ ...BALANCES, balances: [] })
    renderCard()

    expect(await screen.findByText('Nessun tipo di assenza scala un saldo.')).toBeInTheDocument()
  })

  it('renders nothing on 403', async () => {
    fetchBalancesMock.mockRejectedValue(httpError(403, {}))
    const { container } = renderCard()

    await waitFor(() => expect(fetchBalancesMock).toHaveBeenCalled())
    await waitFor(() => expect(container).toBeEmptyDOMElement())
  })

  it('hides the allowance action without manageAll', async () => {
    renderCard()
    await screen.findByText('Ferie')

    expect(screen.queryByRole('button', { name: /Modifica spettanza/ })).not.toBeInTheDocument()
  })

  it('saves the allowance converting hours to minutes', async () => {
    canMock.mockImplementation((permission) => permission === 'absence-requests.manageAll')
    saveAllowanceMock.mockResolvedValue(BALANCES.balances[0])
    renderCard()

    fireEvent.click(await screen.findByRole('button', { name: 'Modifica spettanza Ferie' }))
    const dialog = await screen.findByRole('dialog')
    const annual = within(dialog).getByLabelText('Spettanza annua (ore)')
    const carried = within(dialog).getByLabelText('Riporto (ore, anche negativo)')
    fireEvent.change(annual, { target: { value: '160' } })
    fireEvent.change(carried, { target: { value: '-7.5' } })
    fireEvent.click(within(dialog).getByRole('button', { name: 'Salva' }))

    await waitFor(() =>
      expect(saveAllowanceMock).toHaveBeenCalledWith({
        user_id: 7,
        absence_type_id: 1,
        year: BALANCES.year,
        annual_minutes: 9600,
        carried_over_minutes: -450,
      }),
    )
  })

  it('wires a 422 onto the field with the accessible error triad', async () => {
    canMock.mockReturnValue(true)
    saveAllowanceMock.mockRejectedValue(
      httpError(422, { errors: { annual_minutes: ['Valore non valido dal server'] } }),
    )
    renderCard()

    fireEvent.click(await screen.findByRole('button', { name: 'Modifica spettanza Ferie' }))
    const dialog = await screen.findByRole('dialog')
    fireEvent.click(within(dialog).getByRole('button', { name: 'Salva' }))

    expect(await within(dialog).findByRole('alert')).toHaveTextContent('Valore non valido dal server')
    expect(within(dialog).getByLabelText('Spettanza annua (ore)')).toHaveAttribute('aria-invalid', 'true')
  })
})
