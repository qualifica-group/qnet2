import { act, type ReactNode } from 'react'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, screen, within } from '@testing-library/react'
import i18n from '@/i18n'
import {
  PAID_LINE,
  CONTRACT_DATA,
  CONTRACT_DATA_HIDDEN_COMMISSIONS,
  RECEIVED_LINE,
} from '@/features/work-order-contract-data/contract-data-fixtures'
import {
  STATUS_ITEMS,
  forceFocusVisible,
  hintButtons,
  lineRow,
  renderSection,
} from '@/features/work-order-contract-data/contract-data-test-helpers'
import type { WorkOrderContractData } from '@/features/work-order-contract-data/types'

vi.mock('@/components/detail/record-link', () => ({
  RecordLink: ({ domain, id, children }: { domain: string; id: number; children: ReactNode }) => (
    <a href={`/${domain}/${id}`}>{children}</a>
  ),
}))

const fetchMock = vi.fn<(id: number) => Promise<WorkOrderContractData>>()
vi.mock('@/features/work-order-contract-data/api', () => ({
  fetchWorkOrderContractData: (id: number) => fetchMock(id),
  updateContractDataLine: vi.fn(),
}))

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>('@/features/for-select/api')
  return { ...actual, fetchForSelect: (resource: string, params: unknown) => fetchForSelectMock(resource, params) }
})

const hover = (element: HTMLElement) => fireEvent.pointerEnter(element, { pointerType: 'mouse' })

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchMock.mockReset()
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockResolvedValue({
    items: STATUS_ITEMS,
    pagination: { offset: 0, limit: 25, total: null, has_more: false },
    export_link: null,
  })
  fetchMock.mockResolvedValue(CONTRACT_DATA)
})

describe('WorkOrderContractDataSection - states', () => {
  it('shows a loading status, then the table', async () => {
    renderSection(false)

    expect(screen.getByRole('status', { name: 'Loading contract data' })).toBeInTheDocument()
    expect(await screen.findByRole('table')).toBeInTheDocument()
  })

  it('shows an error with retry', async () => {
    fetchMock.mockRejectedValueOnce(new Error('boom'))
    renderSection(false)

    expect(await screen.findByRole('alert')).toHaveTextContent('Unable to load the work order contract data.')
    fireEvent.click(screen.getByRole('button', { name: 'Retry' }))

    expect(await screen.findByRole('table')).toBeInTheDocument()
  })

  it('shows the empty state without a table', async () => {
    fetchMock.mockResolvedValue({ ...CONTRACT_DATA, lines: [] })
    renderSection(false)

    expect(await screen.findByText('The work order has no product lines.')).toBeInTheDocument()
    expect(screen.queryByRole('table')).not.toBeInTheDocument()
  })
})

describe('WorkOrderContractDataSection - layout', () => {
  it('has the full column names and no formula text under the rows', async () => {
    renderSection(false)
    await screen.findByRole('table')

    for (const name of ['Product', 'Net amount', 'Supplier commission', 'Net of commissions', 'Effective revenue', 'Payment']) {
      expect(screen.getByRole('columnheader', { name })).toBeInTheDocument()
    }
    expect(screen.queryByText(/→ revenue \d/)).not.toBeInTheDocument()
    expect(screen.queryByText(/Net of commissions: /)).not.toBeInTheDocument()
  })

  it('keeps code, name and typology badge in the same cell of the line', async () => {
    renderSection(false)
    await screen.findByRole('table')

    const cell = screen.getByRole('rowheader', { name: /CON-001/ })
    expect(within(cell).getByText('CON-001')).toBeInTheDocument()
    expect(within(cell).getByText('Consulenza qualita')).toHaveAttribute('title', 'Consulenza qualita')
    expect(within(cell).getByRole('link', { name: 'Consulenza qualita' })).toHaveAttribute('href', '/products/1')
    expect(within(cell).getByText('Consulenza')).toBeInTheDocument()
    expect(within(cell).getByText('Consulenza')).toHaveClass('bg-blue-100')
    expect(within(screen.getByRole('rowheader', { name: /ENT-001/ })).getByText('Ente')).toHaveClass('bg-violet-100')
  })

  it('keeps the table inside its own horizontal scroll container', async () => {
    renderSection(false)

    expect((await screen.findByRole('table')).parentElement).toHaveClass('overflow-x-auto')
  })

  it('drops the commission columns and totals when commissions are not visible', async () => {
    fetchMock.mockResolvedValue(CONTRACT_DATA_HIDDEN_COMMISSIONS)
    renderSection(false)
    await screen.findByRole('table')

    expect(screen.queryByRole('columnheader', { name: 'Supplier commission' })).not.toBeInTheDocument()
    expect(screen.queryByRole('columnheader', { name: 'Net of commissions' })).not.toBeInTheDocument()
    expect(screen.getAllByRole('definition')).toHaveLength(2)

    hover(hintButtons(lineRow('ENT-001'))[1])
    expect(await screen.findByText('Revenue 200.00')).toBeInTheDocument()
  })
})

describe('WorkOrderContractDataSection - calculation hints', () => {
  it('explains the net amount on hover', async () => {
    renderSection(false)
    await screen.findByRole('table')

    hover(hintButtons(lineRow('CON-001'))[0])

    expect(await screen.findByText('Quantity × unit price.')).toBeInTheDocument()
    expect(screen.getByText('2 × 500.00 = 1,000.00')).toBeInTheDocument()
  })

  it('explains a paid percentage commission with its base', async () => {
    renderSection(false)
    await screen.findByRole('table')

    hover(hintButtons(lineRow('CON-001'))[1])

    expect(await screen.findByText('Supplier commission paid')).toBeInTheDocument()
    expect(screen.getByText('15% of 1,000.00 = 150.00')).toBeInTheDocument()
    expect(screen.getByText('The base is the line margin.')).toBeInTheDocument()
  })

  it('explains a fixed received commission and a stale base', async () => {
    fetchMock.mockResolvedValue({
      ...CONTRACT_DATA,
      lines: [
        {
          ...RECEIVED_LINE,
          supplier_commission: { commission_type: 'FIXED_AMOUNT', value: '300.0000', base_amount: null, amount: '300.00', is_stale: true },
        },
      ],
    })
    renderSection(false)
    await screen.findByRole('table')

    hover(hintButtons(lineRow('ENT-001'))[1])

    expect(await screen.findByText('Supplier commission received')).toBeInTheDocument()
    expect(screen.getByText('Fixed amount: 300.00')).toBeInTheDocument()
    expect(screen.getAllByText('Amount calculated on a previous base: save the quote again to update it.').length).toBeGreaterThan(0)
    expect(screen.queryByText('The base is the line margin.')).not.toBeInTheDocument()
  })

  it('explains the net of commissions as net amount minus commissions', async () => {
    renderSection(false)
    await screen.findByRole('table')

    hover(hintButtons(lineRow('CON-001'))[2])

    expect(await screen.findByText('1,000.00 − 250.00 = 750.00')).toBeInTheDocument()
  })

  it('explains why the revenue of a paid line is the net amount', async () => {
    renderSection(false)
    await screen.findByRole('table')

    hover(hintButtons(lineRow('CON-001'))[3])

    expect(await screen.findByText('Supplier commission paid: the revenue is the net amount, the commission is a cost.')).toBeInTheDocument()
    expect(screen.getByText('2 × 500.00 = 1,000.00 → revenue 1,000.00')).toBeInTheDocument()
  })

  it('explains why the revenue of a received line is the commission', async () => {
    renderSection(false)
    await screen.findByRole('table')

    hover(hintButtons(lineRow('ENT-001'))[3])

    expect(await screen.findByText('Supplier commission received: the revenue is the commission, not the net amount.')).toBeInTheDocument()
    expect(screen.getByText('10% of 2,000.00 = 200.00 → revenue 200.00')).toBeInTheDocument()
  })

  it('explains a line without a Supplier commission calculation', async () => {
    fetchMock.mockResolvedValue({
      ...CONTRACT_DATA,
      lines: [{ ...PAID_LINE, supplier_commission_direction: null, supplier_commission: null }],
    })
    renderSection(false)
    await screen.findByRole('table')

    hover(hintButtons(lineRow('CON-001'))[2])

    expect(await screen.findByText('Supplier commission not calculated: the revenue is the net amount.')).toBeInTheDocument()
  })

  it('opens on keyboard focus', async () => {
    renderSection(false)
    await screen.findByRole('table')

    forceFocusVisible()
    act(() => hintButtons(lineRow('CON-001'))[0].focus())

    expect(await screen.findByText('Quantity × unit price.')).toBeInTheDocument()
  })

  it('opens on a touch tap and closes on the next tap', async () => {
    renderSection(false)
    await screen.findByRole('table')
    const trigger = hintButtons(lineRow('CON-001'))[0]

    fireEvent.pointerDown(trigger, { pointerType: 'touch' })
    fireEvent.click(trigger)
    expect(await screen.findByText('Quantity × unit price.')).toBeInTheDocument()

    fireEvent.pointerDown(trigger, { pointerType: 'touch' })
    fireEvent.click(trigger)
    expect(screen.queryByText('Quantity × unit price.')).not.toBeInTheDocument()
  })

  it('does not close a hover-opened hint on the click that follows', async () => {
    renderSection(false)
    await screen.findByRole('table')
    const trigger = hintButtons(lineRow('CON-001'))[0]

    hover(trigger)
    fireEvent.pointerDown(trigger, { pointerType: 'mouse' })
    fireEvent.click(trigger)

    expect(await screen.findByText('Quantity × unit price.')).toBeInTheDocument()
  })
})

describe('WorkOrderContractDataSection - KPIs and typologies', () => {
  it('shows one row of KPIs, each explained, and the typology strip with the empty ones dimmed', async () => {
    renderSection(false)
    await screen.findByRole('table')

    const kpis = screen.getAllByRole('definition').map((node) => node.textContent)
    expect(kpis).toEqual(['3,000.00', '1,200.00', '450.00', '2,550.00'])
    expect(screen.getByText('Total revenue', { selector: 'dt' })).toBeInTheDocument()

    const strip = screen.getAllByRole('listitem').map((node) => node.textContent)
    expect(within(screen.getAllByRole('listitem')[2]).getByText('Formazione')).toHaveClass('bg-amber-100')
    expect(strip).toEqual([
      'Consulenza1,000.00 → 1,000.00',
      'Ente2,000.00 → 200.00',
      'Formazione0.00 → 0.00',
    ])
    expect(screen.getByText('Formazione', { selector: 'li span' }).closest('li')).toHaveClass('text-muted-foreground')
    expect(screen.getByText('Ente', { selector: 'li span' }).closest('li')).not.toHaveClass('text-muted-foreground')

    hover(within(screen.getByText('Net of commissions', { selector: 'dt' }).parentElement as HTMLElement).getByRole('button'))
    expect(await screen.findByText('3,000.00 − 450.00 = 2,550.00')).toBeInTheDocument()
  })
})

describe('WorkOrderContractDataSection - warnings (AC-018)', () => {
  it('shows each warning as an icon with accessible text, and the text in its hint', async () => {
    fetchMock.mockResolvedValue({
      ...CONTRACT_DATA,
      lines: [
        { ...RECEIVED_LINE, supplier_commission: null, effective_revenue: '0.00', warnings: ['missing_supplier_commission'] },
        { ...PAID_LINE, warnings: ['stale_commission_base'] },
      ],
    })
    renderSection(false)
    await screen.findByRole('table')

    const missing = screen.getByText('Supplier commission received missing: the revenue is 0.00.')
    expect(missing).toHaveClass('sr-only')
    expect(screen.getByText('Amount calculated on a previous base: save the quote again to update it.')).toHaveClass('sr-only')

    hover(missing.closest('button') as HTMLElement)
    expect(await screen.findByText('Warning')).toBeInTheDocument()
  })
})

describe('WorkOrderContractDataSection - payment display', () => {
  it('shows status and unpaid as badges, the agreement only in the cell hint, and no actions without manage_payments', async () => {
    renderSection(false)
    await screen.findByRole('table')

    expect(screen.getByText('Pagato')).toBeInTheDocument()
    expect(screen.getAllByText('Unpaid')).toHaveLength(1)
    expect(screen.getAllByText('No status')).toHaveLength(1)
    expect(screen.getByText('Pagato').closest('[data-slot="badge"]')).toHaveClass('bg-green-100', 'text-green-700')
    expect(screen.getByText('No status')).toHaveClass('bg-muted')
    expect(screen.getByText('Unpaid')).toHaveClass('bg-destructive')
    expect(screen.queryByText('Saldo a 30 giorni')).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Edit the payment/ })).not.toBeInTheDocument()
    expect(screen.queryByRole('columnheader', { name: 'Actions' })).not.toBeInTheDocument()

    hover(within(lineRow('ENT-001')).getByText('Pagato').closest('button') as HTMLElement)
    expect(await screen.findByText('Saldo a 30 giorni')).toBeInTheDocument()
  })
})
