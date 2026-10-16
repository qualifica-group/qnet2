import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { contracts as contractsEn } from '@/i18n/locales/en-contracts'
import { workOrders as workOrdersEn } from '@/i18n/locales/en-work-orders'
import { ContractMultiProgramDialog } from '@/features/contracts/contract-multi-program-dialog'
import { createMultiQuoteWorkOrder, fetchMultiProgrammableLines } from '@/features/contracts/api'
import type { MultiProgrammableLine, MultiProgrammableLinesData } from '@/features/contracts/types'
import type { WorkOrderDetail } from '@/features/work-orders/types'

/** Spec 0267 AC-015: grouped lines, eligibility + same-branch rule, >=2 offers, payload, 422 on the lines. */

vi.mock('@/features/contracts/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/contracts/api')>('@/features/contracts/api')
  return { ...actual, fetchMultiProgrammableLines: vi.fn(), createMultiQuoteWorkOrder: vi.fn() }
})

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const navigateMock = vi.fn()
vi.mock('react-router-dom', async () => ({
  ...(await vi.importActual<typeof import('react-router-dom')>('react-router-dom')),
  useNavigate: () => navigateMock,
}))

const SUPERVISOR = { id: 21, label: 'Ada Alberti' }

function forSelectPage(items: { id: number; label: string }[]) {
  return {
    data: { pages: [{ items }] },
    isPending: false,
    isError: false,
    fetchNextPage: vi.fn(),
    hasNextPage: false,
    isFetchingNextPage: false,
    refetch: vi.fn(),
  }
}

vi.mock('@/features/for-select/use-for-select', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/use-for-select')>(
    '@/features/for-select/use-for-select',
  )
  return {
    ...actual,
    useForSelect: () => forSelectPage([SUPERVISOR]),
    useForSelectLabels: () => new Map([[SUPERVISOR.id, SUPERVISOR.label]]),
  }
})

const BRANCH_A = { id: 1, name: 'Sicurezza' }
const BRANCH_B = { id: 2, name: 'SOA' }

function line(id: number, contractId: number, name: string, extra: Partial<MultiProgrammableLine> = {}): MultiProgrammableLine {
  return {
    id,
    sort_order: id,
    product: { id, code: `P-${id}`, name, category: null },
    quantity: '1.00',
    unit_of_measure: null,
    work_order: null,
    contract_id: contractId,
    quote_id: contractId * 10,
    branch_root: BRANCH_A,
    multi_quote_eligible: true,
    ...extra,
  }
}

const DATA: MultiProgrammableLinesData = {
  contracts: [
    { id: 1, quote: { id: 10, code: 'QUO-0001', title: null }, registry: { id: 5, name: 'Acme' } },
    { id: 2, quote: { id: 20, code: 'QUO-0002', title: null }, registry: { id: 6, name: 'Beta' } },
  ],
  lines: [
    line(101, 1, 'Corso base'),
    line(102, 1, 'Corso avanzato'),
    line(103, 1, 'Già occupata', { work_order: { id: 9, code: 'COM-0009' }, multi_quote_eligible: false }),
    line(201, 2, 'Visita medica'),
    line(202, 2, 'Altro ramo', { branch_root: BRANCH_B }),
    line(203, 2, 'Senza flag', { multi_quote_eligible: false }),
  ],
}

function renderDialog() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const onOpenChange = vi.fn()
  render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <ContractMultiProgramDialog open onOpenChange={onOpenChange} contractIds={[1, 2]} />
      </MemoryRouter>
    </QueryClientProvider>,
  )
  return { onOpenChange }
}

const box = (name: string) => screen.getByRole('checkbox', { name: `Select ${name}` })
const submitButton = () => screen.getByRole('button', { name: 'Create work order' })

async function fillFields() {
  fireEvent.change(screen.getByLabelText(/^Start date/), { target: { value: '2026-03-01' } })
  fireEvent.click(screen.getByRole('button', { name: /Supervisors/ }))
  fireEvent.click(await screen.findByRole('option', { name: /Ada Alberti/ }))
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
  i18n.addResourceBundle('en', 'translation', { contracts: contractsEn, workOrders: workOrdersEn }, true, true)
})

beforeEach(() => {
  vi.mocked(fetchMultiProgrammableLines).mockReset().mockResolvedValue(DATA)
  vi.mocked(createMultiQuoteWorkOrder).mockReset()
  vi.mocked(toast.success).mockReset()
  vi.mocked(toast.error).mockReset()
  navigateMock.mockReset()
})

describe('ContractMultiProgramDialog (spec 0267)', () => {
  it('groups lines by contract and disables non-eligible ones with the reason', async () => {
    renderDialog()

    const acme = within(await screen.findByRole('region', { name: 'QUO-0001 · Acme' }))
    expect(acme.getByText('Corso base')).toBeInTheDocument()
    expect(within(screen.getByRole('region', { name: 'QUO-0002 · Beta' })).getByText('Visita medica')).toBeInTheDocument()
    expect(box('Già occupata')).toBeDisabled()
    expect(screen.getByText('Already in COM-0009')).toBeInTheDocument()
    expect(box('Senza flag')).toBeDisabled()
    expect(fetchMultiProgrammableLines).toHaveBeenCalledWith([1, 2])
  })

  it('restricts to the branch of the first pick and needs 2 offers to enable submit', async () => {
    renderDialog()
    await screen.findByText('Corso base')

    fireEvent.click(box('Corso base'))
    expect(box('Altro ramo')).toBeDisabled()
    expect(screen.getByText('Other branch')).toBeInTheDocument()
    expect(submitButton()).toBeDisabled()

    fireEvent.click(box('Visita medica'))
    expect(submitButton()).toBeEnabled()
  })

  it('posts the payload and opens the created work order', async () => {
    vi.mocked(createMultiQuoteWorkOrder).mockResolvedValue({ id: 55 } as WorkOrderDetail)
    renderDialog()
    await screen.findByText('Corso base')
    fireEvent.click(box('Corso base'))
    fireEvent.click(box('Visita medica'))
    await fillFields()

    fireEvent.click(submitButton())

    await waitFor(() => expect(navigateMock).toHaveBeenCalledWith('/work-orders/55'))
    expect(createMultiQuoteWorkOrder).toHaveBeenCalledWith({
      contract_ids: [1, 2],
      quote_line_ids: [101, 201],
      title: null,
      type: 'processing',
      start_date: '2026-03-01',
      supervisor_ids: [21],
      task_template_id: null,
    })
    expect(toast.success).toHaveBeenCalled()
  })

  it('shows a 422 on quote_line_ids next to the lines and stays open', async () => {
    vi.mocked(createMultiQuoteWorkOrder).mockRejectedValue(
      Object.assign(new Error('Unprocessable'), {
        isAxiosError: true,
        response: { status: 422, data: { errors: { quote_line_ids: ['Line already in COM-0042.'] } } },
      }),
    )
    renderDialog()
    await screen.findByText('Corso base')
    fireEvent.click(box('Corso base'))
    fireEvent.click(box('Visita medica'))
    await fillFields()

    fireEvent.click(submitButton())

    expect(await screen.findByText('Line already in COM-0042.')).toBeInTheDocument()
    expect(navigateMock).not.toHaveBeenCalled()
    expect(toast.error).not.toHaveBeenCalled()
  })

  it('AC-019: explains inside the dialog which contracts cannot be grouped, without retry', async () => {
    vi.mocked(fetchMultiProgrammableLines).mockRejectedValue(
      Object.assign(new Error('Unprocessable'), {
        isAxiosError: true,
        response: {
          status: 422,
          data: { errors: { contract_ids: ['These contracts do not allow a multi-offer work order: QUO-0018 (Rossi Srl).'] } },
        },
      }),
    )
    renderDialog()

    expect(await screen.findByRole('alert')).toHaveTextContent('QUO-0018 (Rossi Srl)')
    expect(fetchMultiProgrammableLines).toHaveBeenCalledTimes(1)
    expect(screen.queryByRole('button', { name: 'Retry' })).not.toBeInTheDocument()
    expect(submitButton()).toBeDisabled()
  })

  it('falls back to a generic toast on 403', async () => {
    vi.mocked(createMultiQuoteWorkOrder).mockRejectedValue(
      Object.assign(new Error('Forbidden'), { isAxiosError: true, response: { status: 403, data: {} } }),
    )
    renderDialog()
    await screen.findByText('Corso base')
    fireEvent.click(box('Corso base'))
    fireEvent.click(box('Visita medica'))
    await fillFields()

    fireEvent.click(submitButton())

    await waitFor(() => expect(toast.error).toHaveBeenCalled())
  })
})
