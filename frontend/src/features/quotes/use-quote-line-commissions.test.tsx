import { act, renderHook, waitFor } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { toast } from 'sonner'
import { fetchQuoteCommissionDefaults, fetchQuoteCommissionRecipients } from './api'
import type { QuoteCatalogProduct } from './line-editor/types'
import type { QuoteLineFormValues } from './quote-schema'
import type { QuoteCommissionContext, QuoteCommissionRecipientMap } from './types'
import { useQuoteLineCommissions } from './use-quote-line-commissions'

/**
 * Spec 0269 D-4/D-6: the commission behaviour of the Offerte revenue rows,
 * migrated from `quote-lines-field-commissions.test.tsx` (REQUIREMENT
 * CHANGED: the logic left the deleted `QuoteLinesField` for this hook; the
 * per-row product change is now the `single`-mode replacement), plus the new
 * multi-add of the catalogue.
 */

const confirm = vi.hoisted(() => vi.fn())
vi.mock('@/components/confirm-dialog-context', () => ({ useOptionalConfirm: () => confirm }))
vi.mock('./api', () => ({
  fetchQuoteCommissionDefaults: vi.fn(),
  fetchQuoteCommissionRecipients: vi.fn(),
}))
vi.mock('sonner', () => ({ toast: { error: vi.fn() } }))

const existingRow: QuoteLineFormValues = {
  id: 1,
  product_id: 10,
  quantity: 2,
  unit_of_measure: null,
  unit_price: 100,
  vat_rate_id: null,
  commissions: [{
    id: 2,
    recipient_role: 'COMMERCIAL',
    recipient_type: 'referent',
    recipient_id: 4,
    commission_type: 'PERCENTAGE',
    value: 5,
    internal_note: null,
    origin: 'MANUAL_OVERRIDE',
    commission_configuration_id: 1,
  }],
}

const RECIPIENTS: QuoteCommissionRecipientMap = {
  COMMERCIAL: { type: 'referent', id: 4, name: 'Anna' },
  REPORTER: null,
  SUPERVISOR: null,
  SUPPLIER: null,
}

const DEFAULTS = {
  supplier_commission_direction: 'RECEIVED' as const,
  commissions: [{
    recipient_role: 'COMMERCIAL' as const,
    recipient_type: 'referent' as const,
    recipient_id: 4,
    recipient: { id: 4, name: 'Anna' },
    commission_type: 'PERCENTAGE' as const,
    value: '8.0000',
    calculated_amount: '8.00',
    internal_note: 'Default',
    origin: 'PRODUCT' as const,
    commission_configuration_id: 3,
  }],
}

function product(id: number, price: string | null): QuoteCatalogProduct {
  return {
    id,
    label: `Product ${id}`,
    meta: {
      category_id: 1,
      code: `P-${id}`,
      description: null,
      price,
      cost: '30.00',
      vat_rate_id: null,
      vat_rate_name: null,
      vat_rate: null,
      unit_of_measure: null,
      product_typology: null,
    },
  }
}

const context = (commercialId: number | null): QuoteCommissionContext => ({
  quoteId: 9,
  commercialId,
  reporterId: null,
  supervisorId: null,
})

/** `null` = no role context at all (the hook's `undefined`). */
function renderCommissions(onChange = vi.fn(), commissionContext: QuoteCommissionContext | null = context(4)) {
  const view = renderHook(
    ({ ctx }: { ctx: QuoteCommissionContext | undefined }) =>
      useQuoteLineCommissions({ value: [existingRow], onChange, commissionContext: ctx }),
    { initialProps: { ctx: commissionContext ?? undefined } },
  )
  return { ...view, onChange }
}

beforeEach(() => {
  vi.clearAllMocks()
  vi.mocked(fetchQuoteCommissionRecipients).mockResolvedValue(RECIPIENTS)
})

describe('useQuoteLineCommissions — cambio ruoli', () => {
  it('follows a changed quote role onto the commissions already on the line', async () => {
    vi.mocked(fetchQuoteCommissionRecipients).mockResolvedValue({ ...RECIPIENTS, COMMERCIAL: { type: 'referent', id: 77, name: 'Sara' } })
    vi.mocked(fetchQuoteCommissionDefaults).mockResolvedValue({ commissions: [], supplier_commission_direction: null })
    const { rerender, onChange } = renderCommissions()

    await act(async () => rerender({ ctx: context(77) }))

    await waitFor(() => expect(onChange).toHaveBeenCalledWith([
      expect.objectContaining({
        commissions: [expect.objectContaining({ recipient_role: 'COMMERCIAL', recipient_id: 77, recipient: { id: 77, name: 'Sara' } })],
      }),
    ]))
  })

  it('drops a commission whose role lost its holder on the quote', async () => {
    vi.mocked(fetchQuoteCommissionRecipients).mockResolvedValue({ ...RECIPIENTS, COMMERCIAL: null })
    vi.mocked(fetchQuoteCommissionDefaults).mockResolvedValue({ commissions: [], supplier_commission_direction: null })
    const { rerender, onChange } = renderCommissions()

    await act(async () => rerender({ ctx: context(null) }))

    await waitFor(() => expect(onChange).toHaveBeenCalledWith([expect.objectContaining({ commissions: [] })]))
  })
})

describe('useQuoteLineCommissions — sostituzione (riga singola)', () => {
  it('keeps the row untouched when the regeneration is cancelled', async () => {
    confirm.mockResolvedValue(false)
    const { result } = renderCommissions()

    expect(await result.current.defaultsForReplacement(existingRow, product(20, '50.00'))).toBeNull()
    expect(confirm).toHaveBeenCalled()
    expect(fetchQuoteCommissionDefaults).not.toHaveBeenCalled()
  })

  it('loads the new product defaults after a confirmed change', async () => {
    confirm.mockResolvedValue(true)
    vi.mocked(fetchQuoteCommissionDefaults).mockResolvedValue(DEFAULTS)
    const { result } = renderCommissions()

    const defaults = await result.current.defaultsForReplacement(existingRow, product(20, '50.00'))

    expect(fetchQuoteCommissionDefaults).toHaveBeenCalledWith(expect.objectContaining({ quote_id: 9, product_id: 20, line_net_amount: 100 }))
    expect(defaults).toEqual({
      supplierCommissionDirection: 'RECEIVED',
      commissions: [expect.objectContaining({ value: 8, recipient_role: 'COMMERCIAL' })],
    })
  })
})

describe('useQuoteLineCommissions — aggiunta dal catalogo', () => {
  it('AC-004: loads the defaults of every picked product at quantity 1', async () => {
    vi.mocked(fetchQuoteCommissionDefaults).mockResolvedValue(DEFAULTS)
    const { result } = renderCommissions()

    const defaults = await result.current.defaultsForNewRows([product(20, '50.00'), product(21, null)])

    expect(fetchQuoteCommissionDefaults).toHaveBeenCalledWith(expect.objectContaining({ product_id: 20, line_net_amount: 50, commercial_id: 4 }))
    expect(fetchQuoteCommissionDefaults).toHaveBeenCalledWith(expect.objectContaining({ product_id: 21, line_net_amount: 0 }))
    expect(defaults?.get(20)?.supplierCommissionDirection).toBe('RECEIVED')
    expect(defaults?.size).toBe(2)
  })

  it('AC-005: one failed request adds nothing and shows the error', async () => {
    vi.mocked(fetchQuoteCommissionDefaults).mockResolvedValueOnce(DEFAULTS).mockRejectedValueOnce(new Error('down'))
    const { result } = renderCommissions()

    expect(await result.current.defaultsForNewRows([product(20, '50.00'), product(21, '10.00')])).toBeNull()
    expect(toast.error).toHaveBeenCalled()
  })

  it('brings an empty block without a role context, with no request', async () => {
    const { result } = renderCommissions(vi.fn(), null)

    const defaults = await result.current.defaultsForNewRows([product(20, '50.00')])

    expect(fetchQuoteCommissionDefaults).not.toHaveBeenCalled()
    expect(defaults?.get(20)).toEqual({ commissions: [], supplierCommissionDirection: null })
  })
})

describe('useQuoteLineCommissions — fornitore (spec 0320 D-7)', () => {
  const withSuppliers = (): QuoteCatalogProduct => {
    const item = product(20, '50.00')
    return {
      ...item,
      meta: {
        ...item.meta,
        suppliers: [
          { supplier_id: 5, name: 'Acme', price: '130.00', cost: '30.00', is_default: false },
          { supplier_id: 6, name: 'Beta', price: '135.00', cost: '35.00', is_default: true },
        ],
      },
    }
  }

  it('asks the defaults of a new row with its default supplier', async () => {
    vi.mocked(fetchQuoteCommissionDefaults).mockResolvedValue(DEFAULTS)
    const { result } = renderCommissions()

    await result.current.defaultsForNewRows([withSuppliers()])

    expect(fetchQuoteCommissionDefaults).toHaveBeenCalledWith(expect.objectContaining({ product_id: 20, supplier_id: 6 }))
  })

  it('computes the new row net amount from the default supplier price, not the standard one (rev.2 D-17)', async () => {
    vi.mocked(fetchQuoteCommissionDefaults).mockResolvedValue(DEFAULTS)
    const { result } = renderCommissions()

    await result.current.defaultsForNewRows([withSuppliers()])

    expect(fetchQuoteCommissionDefaults).toHaveBeenCalledWith(expect.objectContaining({ line_net_amount: 135 }))
  })

  it('asks the defaults of the row with the new supplier, after a confirmed change', async () => {
    confirm.mockResolvedValue(true)
    vi.mocked(fetchQuoteCommissionDefaults).mockResolvedValue(DEFAULTS)
    const { result } = renderCommissions()

    const defaults = await result.current.defaultsForSupplier({ ...existingRow, supplier_id: 5 }, 6)

    expect(confirm).toHaveBeenCalled()
    expect(fetchQuoteCommissionDefaults).toHaveBeenCalledWith(
      expect.objectContaining({ product_id: 10, supplier_id: 6, line_net_amount: 200 }),
    )
    expect(defaults?.supplierCommissionDirection).toBe('RECEIVED')
  })

  it('changes nothing when the regeneration is cancelled', async () => {
    confirm.mockResolvedValue(false)
    const { result } = renderCommissions()

    expect(await result.current.defaultsForSupplier(existingRow, 6)).toBeNull()
    expect(fetchQuoteCommissionDefaults).not.toHaveBeenCalled()
  })

  it('re-syncs the commissions of a row with the supplier it carries when a role changes', async () => {
    vi.mocked(fetchQuoteCommissionDefaults).mockResolvedValue({ commissions: [], supplier_commission_direction: null })
    const { rerender } = renderHook(
      ({ ctx }: { ctx: QuoteCommissionContext }) =>
        useQuoteLineCommissions({ value: [{ ...existingRow, supplier_id: 5 }], onChange: vi.fn(), commissionContext: ctx }),
      { initialProps: { ctx: context(4) } },
    )

    await act(async () => rerender({ ctx: context(77) }))

    await waitFor(() => expect(fetchQuoteCommissionRecipients).toHaveBeenCalledWith(expect.objectContaining({ supplier_id: 5 })))
  })
})
