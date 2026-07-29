import { act, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { QuoteLinesField } from './quote-lines-field'
import { fetchQuoteCommissionDefaults } from './api'

const confirm = vi.hoisted(() => vi.fn())
vi.mock('@/components/confirm-dialog-context', () => ({ useOptionalConfirm: () => confirm }))
vi.mock('./api', () => ({ fetchQuoteCommissionDefaults: vi.fn() }))
vi.mock('sonner', () => ({ toast: { error: vi.fn() } }))
vi.mock('./quote-line-row', () => ({
  QuoteLineRow: (props: {
    onChangeProduct: (id: number, item: unknown) => void
    onRemove: () => void
    onChangeField: (patch: unknown) => void
  }) => (
    <div>
      <button type="button" onClick={() => props.onChangeProduct(20, {
        id: 20,
        label: 'New product',
        meta: {
          code: 'P-20',
          price: '50.00',
          cost: '30.00',
          vat_rate_id: null,
          vat_rate_name: null,
          vat_rate: null,
        },
      })}>pick new product</button>
      <button type="button" onClick={props.onRemove}>remove row</button>
      <button type="button" onClick={() => props.onChangeField({ quantity: 3 })}>change row</button>
    </div>
  ),
}))

const existingRow = {
  id: 1,
  product_id: 10,
  quantity: 2,
  unit_price: 100,
  vat_rate_id: null,
  commissions: [{
    id: 2,
    recipient_role: 'COMMERCIAL' as const,
    recipient_type: 'referent' as const,
    recipient_id: 4,
    commission_type: 'PERCENTAGE' as const,
    value: 5,
    internal_note: null,
    origin: 'MANUAL_OVERRIDE' as const,
    commission_configuration_id: 1,
  }],
}

function renderField(onChange = vi.fn(), variant: 'revenue' | 'cost' = 'revenue') {
  render(
    <QuoteLinesField
      value={[existingRow]}
      onChange={onChange}
      variant={variant}
      disabled={false}
      knownProducts={[]}
      knownVatRates={[]}
      vatRatePercentFor={() => null}
      rememberVatRatePercent={vi.fn()}
      commissionContext={{
        quoteId: 9,
        commercialId: 4,
        reporterId: null,
        supervisorId: null,
      }}
    />,
  )
  return onChange
}

describe('QuoteLinesField commission defaults', () => {
  beforeEach(() => vi.clearAllMocks())

  it('preserves product and commissions when regeneration is cancelled', async () => {
    confirm.mockResolvedValue(false)
    const onChange = renderField()
    fireEvent.click(screen.getByRole('button', { name: 'pick new product' }))
    await waitFor(() => expect(confirm).toHaveBeenCalled())
    expect(fetchQuoteCommissionDefaults).not.toHaveBeenCalled()
    expect(onChange).not.toHaveBeenCalled()
  })

  it('loads defaults and replaces snapshots after confirmed product change', async () => {
    confirm.mockResolvedValue(true)
    vi.mocked(fetchQuoteCommissionDefaults).mockResolvedValue([{
      recipient_role: 'COMMERCIAL',
      recipient_type: 'referent',
      recipient_id: 4,
      recipient: { id: 4, name: 'Anna' },
      commission_type: 'PERCENTAGE',
      value: '8.0000',
      calculated_amount: '8.00',
      internal_note: 'Default',
      origin: 'PRODUCT',
      commission_configuration_id: 3,
    }])
    const onChange = renderField()
    await act(async () => fireEvent.click(screen.getByRole('button', { name: 'pick new product' })))
    await waitFor(() => expect(fetchQuoteCommissionDefaults).toHaveBeenCalledWith(expect.objectContaining({
      quote_id: 9,
      product_id: 20,
      line_net_amount: 100,
    })))
    expect(onChange).toHaveBeenCalledWith([
      expect.objectContaining({
        product_id: 20,
        unit_price: 50,
        commissions: [expect.objectContaining({ value: 8 })],
      }),
    ])
  })

  it('changes a cost product without invoking commission confirmation or defaults', async () => {
    const onChange = renderField(vi.fn(), 'cost')

    await act(async () => fireEvent.click(screen.getByRole('button', { name: 'pick new product' })))

    expect(confirm).not.toHaveBeenCalled()
    expect(fetchQuoteCommissionDefaults).not.toHaveBeenCalled()
    expect(onChange).toHaveBeenCalledWith([
      expect.objectContaining({
        product_id: 20,
        unit_price: 30,
      }),
    ])
  })
})
