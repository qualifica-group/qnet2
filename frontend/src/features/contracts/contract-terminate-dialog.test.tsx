import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { contracts as contractsEn } from '@/i18n/locales/en-contracts'
import { ContractTerminateDialog } from '@/features/contracts/contract-terminate-dialog'
import { terminateContract } from '@/features/contracts/api'
import type { ContractDetail } from '@/features/contracts/types'

/**
 * AC-045: submitting "Disdici contratto" without a reason or without a date
 * must NOT start the mutation, and the error is announced with `role="alert"`
 * wired via `aria-describedby`/`aria-invalid` (the shared `Form`/`FormField`
 * primitives, not manual ARIA).
 */

vi.mock('@/features/contracts/api', () => ({
  terminateContract: vi.fn(),
}))

// The picker mounts only while the dialog is open; stubbed to a plain
// trigger button so this suite exercises the dialog's own validation, not
// the async for-select network path (already covered by
// `async-paginated-select.test.tsx`).
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({ labels }: { labels: { triggerLabel: string } }) => (
    <button type="button">{labels.triggerLabel}</button>
  ),
}))

function contract(overrides: Partial<ContractDetail> = {}): ContractDetail {
  return {
    id: 1,
    quote_id: 3,
    quote: {
      id: 3,
      code: 'QUO-0003',
      title: 'Accordo Acme',
      created_at: '2026-01-05T00:00:00Z',
      revenue_net: '1000.00',
      revenue_vat: '220.00',
      revenue_gross: '1220.00',
      cost_net: '400.00',
      margin_net: '600.00',
    },
    registry: null,
    opportunity: null,
    company: null,
    company_site: null,
    operational_site: null,
    commercial: null,
    reporter: null,
    supervisor: null,
    payment_method: null,
    contract_status_id: 1,
    contract_status: { id: 1, name: 'Programmato', color: 'blue', group: 'pending' },
    accepted_at: '2026-01-10',
    validated_at: null,
    renewal_date: null,
    expiry_date: null,
    terminated_at: null,
    termination_reason: null,
    payment_notes: null,
    comments: null,
    validated_by: null,
    terminated_by: null,
    suspended_at: null,
    status_before_suspension: null,
    is_suspended: false,
    alert: null,
    days_to_expiry: null,
    days_to_renewal: null,
    offer_lines: [],
    summary: {
      revenue: { net: '0.00', vat: '0.00', gross: '0.00' },
      cost: { net: '0.00', vat: '0.00', gross: '0.00' },
      margin: { net: '0.00' },
    },
    created_at: '2026-01-10T00:00:00Z',
    updated_at: '2026-01-10T00:00:00Z',
    ...overrides,
  }
}

function renderDialog() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const onOpenChange = vi.fn()
  const onTerminated = vi.fn()
  render(
    <QueryClientProvider client={client}>
      <ContractTerminateDialog open contract={contract()} onOpenChange={onOpenChange} onTerminated={onTerminated} />
    </QueryClientProvider>,
  )
  return { onOpenChange, onTerminated }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
  // `en-contracts.ts`/`it-contracts.ts` are not yet merged into `en.ts`/`it.ts`
  // (MT-09's job): registered here at runtime, mirrors the wave-1
  // `document-layouts` pre-merge test pattern.
  i18n.addResourceBundle('en', 'translation', { contracts: contractsEn }, true, true)
})

beforeEach(() => {
  vi.mocked(terminateContract).mockReset()
})

describe('ContractTerminateDialog — validation (AC-045)', () => {
  it('does not submit and announces an accessible error when the reason is empty', async () => {
    renderDialog()

    fireEvent.change(screen.getByLabelText(/^Termination date/), { target: { value: '2026-01-15' } })
    fireEvent.click(screen.getByRole('button', { name: 'Terminate' }))

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent('Reason is required.')

    const reasonField = screen.getByLabelText(/^Reason/)
    expect(reasonField).toHaveAttribute('aria-invalid', 'true')
    expect(reasonField.getAttribute('aria-describedby')).toContain(alert.id)
    expect(terminateContract).not.toHaveBeenCalled()
  })

  it('does not submit and announces an accessible error when the date is empty', async () => {
    renderDialog()

    // The date defaults to today (`terminateContractDefaultValues`): clear it explicitly.
    fireEvent.change(screen.getByLabelText(/^Termination date/), { target: { value: '' } })
    fireEvent.change(screen.getByLabelText(/^Reason/), { target: { value: 'Cliente insolvente' } })
    fireEvent.click(screen.getByRole('button', { name: 'Terminate' }))

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent('Termination date is required.')

    const dateField = screen.getByLabelText(/^Termination date/)
    expect(dateField).toHaveAttribute('aria-invalid', 'true')
    expect(dateField.getAttribute('aria-describedby')).toContain(alert.id)
    expect(terminateContract).not.toHaveBeenCalled()
  })

  it('submits when both date and reason are provided', async () => {
    vi.mocked(terminateContract).mockResolvedValue(contract({ terminated_at: '2026-01-15' }))

    renderDialog()

    fireEvent.change(screen.getByLabelText(/^Termination date/), { target: { value: '2026-01-15' } })
    fireEvent.change(screen.getByLabelText(/^Reason/), { target: { value: 'Cliente insolvente' } })
    fireEvent.click(screen.getByRole('button', { name: 'Terminate' }))

    await screen.findByText('Terminate', { selector: 'button' })
    expect(terminateContract).toHaveBeenCalledWith(1, {
      terminated_at: '2026-01-15',
      termination_reason: 'Cliente insolvente',
    })
  })
})
