import type { ReactNode } from 'react'
import { beforeAll, describe, expect, it, vi } from 'vitest'
import { screen, within } from '@testing-library/react'
import i18n from '@/i18n'
import { CONTRACT_DATA_HIDDEN_FIGURES } from '@/features/work-order-contract-data/contract-data-fixtures'
import { lineRow, renderSection } from '@/features/work-order-contract-data/contract-data-test-helpers'

vi.mock('@/components/detail/record-link', () => ({
  RecordLink: ({ domain, id, children }: { domain: string; id: number; children: ReactNode }) => (
    <a href={`/${domain}/${id}`}>{children}</a>
  ),
}))

vi.mock('@/features/work-order-contract-data/api', () => ({
  fetchWorkOrderContractData: () => Promise.resolve(CONTRACT_DATA_HIDDEN_FIGURES),
  updateContractDataLine: vi.fn(),
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('WorkOrderContractDataSection - without the commessa costs (spec 0380)', () => {
  it('shows only product and payment: no figure column, no KPI, no amount', async () => {
    renderSection(false)
    await screen.findByRole('table')

    expect(screen.getAllByRole('columnheader').map((header) => header.getAttribute('aria-label'))).toEqual([
      'Product',
      'Payment',
    ])
    expect(screen.queryAllByRole('definition')).toHaveLength(0)
    expect(screen.queryByText(/Revenue by typology/)).not.toBeInTheDocument()
    expect(within(lineRow('ENT-001')).queryByText(/\d[.,]\d{2}/)).not.toBeInTheDocument()
    expect(within(lineRow('ENT-001')).getByText('Pagato')).toBeInTheDocument()
  })

  it('keeps the payment editor for whoever manages payments', async () => {
    renderSection(true)
    await screen.findByRole('table')

    expect(screen.getAllByRole('button', { name: /Edit the payment/ })).toHaveLength(2)
  })
})
