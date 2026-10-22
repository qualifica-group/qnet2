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

  it('renders a tile per type with days + hours, the accrued bar and flags a negative residual', async () => {
    renderCard()

    expect(await screen.findByText('Ferie')).toBeInTheDocument()
    expect(screen.getByText('8 g 7 h 20 min')).toBeInTheDocument()
    expect(screen.getByText('20 g')).toBeInTheDocument()
    expect(screen.getByText('Residuo negativo')).toBeInTheDocument()
    expect(screen.getByText('-1 h 30 min')).toBeInTheDocument()
    expect(screen.getByRole('progressbar', { name: 'Goduto 1 g 2 h su 10 g 3 h 20 min maturati' })).toBeInTheDocument()
  })

  it('notes when the allowance comes from the type default', async () => {
    fetchBalancesMock.mockResolvedValue({
      ...BALANCES,
      balances: [{ ...BALANCES.balances[0], source: 'default' }, BALANCES.balances[1]],
    })
    renderCard()

    await screen.findByText('Ferie')
    expect(screen.getAllByText('Spettanza da default del tipo')).toHaveLength(1)
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

  it('saves the allowance converting days to minutes with the daily hours of the person', async () => {
    canMock.mockImplementation((permission) => permission === 'absence-requests.manageAll')
    saveAllowanceMock.mockResolvedValue(BALANCES.balances[0])
    renderCard()

    fireEvent.click(await screen.findByRole('button', { name: 'Modifica spettanza Ferie' }))
    const dialog = await screen.findByRole('dialog')
    fireEvent.change(within(dialog).getByLabelText('Spettanza annua (giorni)'), { target: { value: '22.5' } })
    fireEvent.change(within(dialog).getByLabelText('Riporto (ore, anche negativo)'), { target: { value: '-7.5' } })
    fireEvent.click(within(dialog).getByRole('button', { name: 'Salva' }))

    await waitFor(() =>
      expect(saveAllowanceMock).toHaveBeenCalledWith({
        user_id: 7,
        absence_type_id: 1,
        year: BALANCES.year,
        annual_minutes: 10800,
        carried_over_minutes: -450,
      }),
    )
  })

  it('sends hours when the unit is hours', async () => {
    canMock.mockImplementation((permission) => permission === 'absence-requests.manageAll')
    saveAllowanceMock.mockResolvedValue(BALANCES.balances[0])
    renderCard()

    fireEvent.click(await screen.findByRole('button', { name: 'Modifica spettanza Ferie' }))
    const dialog = await screen.findByRole('dialog')
    fireEvent.click(within(dialog).getByRole('radio', { name: 'Ore' }))
    fireEvent.change(within(dialog).getByLabelText('Spettanza annua (ore)'), { target: { value: '160' } })
    fireEvent.click(within(dialog).getByRole('button', { name: 'Salva' }))
    await waitFor(() => expect(saveAllowanceMock).toHaveBeenCalledWith(expect.objectContaining({ annual_minutes: 9600 })))
  })

  it('sends null with "use the type default"', async () => {
    canMock.mockImplementation((permission) => permission === 'absence-requests.manageAll')
    saveAllowanceMock.mockResolvedValue(BALANCES.balances[0])
    renderCard()

    fireEvent.click(await screen.findByRole('button', { name: 'Modifica spettanza Ferie' }))
    const dialog = await screen.findByRole('dialog')
    fireEvent.click(within(dialog).getByRole('checkbox', { name: 'Usa il default del tipo' }))
    fireEvent.click(within(dialog).getByRole('button', { name: 'Salva' }))

    await waitFor(() => expect(saveAllowanceMock).toHaveBeenCalledWith(expect.objectContaining({ annual_minutes: null })))
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
    expect(within(dialog).getByLabelText('Spettanza annua (giorni)')).toHaveAttribute('aria-invalid', 'true')
  })
})
