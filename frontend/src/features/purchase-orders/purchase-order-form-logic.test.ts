import { describe, expect, it, vi } from 'vitest'
import { AxiosError, type AxiosResponse } from 'axios'
import type { TFunction } from 'i18next'
import { makeOrderLine, makePurchaseOrder } from '@/features/purchase-orders/purchase-order-fixtures'
import {
  buildOrderHeaderPatch,
  buildOrderLinesPayload,
  buildOrderPayload,
  emptyOrderFormValues,
  newOrderLineValues,
  toOrderFormValues,
} from '@/features/purchase-orders/purchase-order-payload'
import { isOrderReadOnly, toOrderResourcePermissions } from '@/features/purchase-orders/purchase-order-permissions'
import { buildPurchaseOrderSchema, type PurchaseOrderFormValues } from '@/features/purchase-orders/purchase-order-schema'
import { applyOrderServerErrors } from '@/features/purchase-orders/purchase-order-server-errors'
import { extractVat, MissingVatRateError, previewLineAmounts, previewTotals } from '@/features/purchase-requests/purchase-request-amounts'

const t = ((key: string) => key) as unknown as TFunction
const schema = buildPurchaseOrderSchema(t)

function validValues(overrides: Partial<PurchaseOrderFormValues> = {}): PurchaseOrderFormValues {
  return {
    ...emptyOrderFormValues(new Date(2026, 9, 12)),
    supplier_id: 8,
    company_id: 1,
    company_site_id: 2,
    description: 'Monthly consulting',
    lines: [newOrderLineValues({ description: 'Consulting', cost_type_id: 1, unit_price: 100 })],
    ...overrides,
  }
}

describe('purchase order amounts preview (AC-019)', () => {
  it('computes a line as round(qty*price) plus VAT on the taxable amount', () => {
    expect(previewLineAmounts({ quantity: 3, unit_price: 10.1, vat_rate_percent: 22 })).toEqual({
      taxable: 30.3,
      vat: 6.67,
      total: 36.97,
    })
  })

  it('sums taxable, VAT and total over the lines', () => {
    const totals = previewTotals([
      { quantity: 2, unit_price: 100, vat_rate_percent: 22 },
      { quantity: 1, unit_price: 50, vat_rate_percent: null },
    ])
    expect(totals).toEqual({ taxable: 250, vat: 44, total: 294 })
  })

  it('extracts the VAT from a gross price (122.00 at 22% -> 100.00) and refuses a missing rate', () => {
    expect(extractVat(122, 22)).toBe(100)
    expect(() => extractVat(122, null)).toThrow(MissingVatRateError)
    expect(() => extractVat(122, 0)).toThrow(MissingVatRateError)
  })
})

describe('purchase order schema (AC-019)', () => {
  it('accepts a complete order', () => {
    expect(schema.safeParse(validValues()).success).toBe(true)
  })

  it('requires supplier, company, company site, date, description, lines and a cost type per line', () => {
    const result = schema.safeParse(
      validValues({
        supplier_id: null,
        company_id: null,
        company_site_id: null,
        order_date: '',
        description: ' ',
        lines: [newOrderLineValues({ description: 'x' })],
      }),
    )
    expect(result.success).toBe(false)
    const messages = result.error?.issues.map((issue) => issue.message) ?? []
    expect(messages).toEqual(
      expect.arrayContaining([
        'purchaseOrderForm.errors.supplierRequired',
        'purchaseOrderForm.errors.companyRequired',
        'purchaseOrderForm.errors.companySiteRequired',
        'purchaseOrderForm.errors.orderDateRequired',
        'purchaseOrderForm.errors.descriptionRequired',
        'purchaseOrderForm.errors.costTypeRequired',
      ]),
    )
    expect(schema.safeParse(validValues({ lines: [] })).success).toBe(false)
  })

  it('bounds the copies to 1..12', () => {
    expect(schema.safeParse(validValues({ copies: 12 })).success).toBe(true)
    expect(schema.safeParse(validValues({ copies: 13 })).success).toBe(false)
    expect(schema.safeParse(validValues({ copies: 0 })).success).toBe(false)
    expect(schema.safeParse(validValues({ copies: Number.NaN })).success).toBe(false)
  })

  it('requires a positive quantity and a non-negative price', () => {
    const line = newOrderLineValues({ description: 'x', cost_type_id: 1 })
    expect(schema.safeParse(validValues({ lines: [{ ...line, quantity: 0 }] })).success).toBe(false)
    expect(schema.safeParse(validValues({ lines: [{ ...line, unit_price: -1 }] })).success).toBe(false)
  })
})

describe('purchase order payload (AC-019)', () => {
  it('create sends copies, no line ids and no UI-only fields', () => {
    const payload = buildOrderPayload(validValues({ copies: 3, general_notes: '  ' }))

    expect(payload.copies).toBe(3)
    expect(payload.general_notes).toBeNull()
    expect(payload).not.toHaveProperty('supplier_qualified')
    expect(payload.lines[0]).not.toHaveProperty('id')
    expect(payload.lines[0]).not.toHaveProperty('purchase_request_id')
    expect(payload.lines[0]).toMatchObject({ cost_type_id: 1, unit_price: 100, quantity: 1 })
  })

  it('a line edit sends the whole set with the persisted line ids (spec 0257 D-7)', () => {
    const order = makePurchaseOrder({
      lines: [makeOrderLine({ id: 11 }), makeOrderLine({ id: 12, position: 2, description: 'Second' })],
    })
    const values = toOrderFormValues(order)
    values.lines.push(newOrderLineValues({ description: 'New', cost_type_id: 1 }))

    expect(buildOrderLinesPayload(values.lines).map((line) => line.id)).toEqual([11, 12, undefined])
  })

  it('a header edit sends only the changed keys, sectors compared as a set (spec 0257 D-6)', () => {
    const order = makePurchaseOrder({ sectors: [{ id: 4, name: 'Health' }, { id: 5, name: 'Energy' }] })
    const persisted = toOrderFormValues(order)

    expect(buildOrderHeaderPatch({ ...persisted, sector_ids: [5, 4] }, persisted)).toEqual({})
    expect(buildOrderHeaderPatch({ ...persisted, description: 'Toner', general_notes: '  ' }, persisted)).toEqual({ description: 'Toner' })
    expect(buildOrderHeaderPatch({ ...persisted, sector_ids: [4] }, persisted)).toEqual({ sector_ids: [4] })
  })

  it('hydrates the form from the order: the RDA link, the supplier qualification and the VAT percentage', () => {
    const values = toOrderFormValues(
      makePurchaseOrder({
        supplier: { id: 8, name: 'Acme', email: null, is_qualified_supplier: false },
        sectors: [{ id: 4, name: 'Health' }],
        lines: [makeOrderLine({ purchase_request_line: { id: 70, purchase_request_id: 15 } })],
      }),
    )

    expect(values.supplier_qualified).toBe(false)
    expect(values.sector_ids).toEqual([4])
    expect(values.lines[0]).toMatchObject({ purchase_request_id: 15, vat_rate_percent: 22, cost_type_id: 1 })
  })

  it('refuses to build a payload without a cost type', () => {
    expect(() => buildOrderPayload(validValues({ lines: [newOrderLineValues({ description: 'x' })] }))).toThrow()
  })
})

describe('purchase order permissions', () => {
  it('locks the whole order outside draft/in_validation or without update', () => {
    expect(isOrderReadOnly(makePurchaseOrder())).toBe(false)
    expect(isOrderReadOnly(makePurchaseOrder({ status: 'in_validation' }))).toBe(false)
    expect(isOrderReadOnly(makePurchaseOrder({ status: 'approved' }))).toBe(true)
    const noUpdate = makePurchaseOrder()
    noUpdate.abilities = { ...noUpdate.abilities, update: false }
    expect(isOrderReadOnly(noUpdate)).toBe(true)
  })

  it('maps field_permissions and forces every field read-only on a locked order', () => {
    const open = toOrderResourcePermissions(
      makePurchaseOrder({ field_permissions: { description: { visible: true, editable: false, required: true } } }),
    )
    expect(open.fields.description).toMatchObject({ editable: false, readonly: true, required: true })
    expect(open.fields.supplier_id).toMatchObject({ editable: true })

    const locked = toOrderResourcePermissions(makePurchaseOrder({ status: 'approved' }))
    expect(locked.fields.supplier_id).toMatchObject({ editable: false, readonly: true })
    expect(locked.resource.update).toBe(false)
  })
})

function unprocessable(errors: Record<string, string[]>): AxiosError {
  return new AxiosError('invalid', '422', undefined, undefined, {
    status: 422,
    data: { errors },
  } as AxiosResponse)
}

describe('purchase order server errors (AC-019)', () => {
  it('maps header, line and copies errors onto inputs and returns the unmapped ones', () => {
    const setError = vi.fn()
    const unmapped = applyOrderServerErrors(
      unprocessable({
        supplier_id: ['not a supplier'],
        company_site_id: ['wrong company'],
        copies: ['too many'],
        'lines.1.cost_type_id': ['inactive'],
        'lines.0.quantity': ['gt 0'],
        'lines.2.id': ['another order'],
        lines: ['at least one'],
      }),
      setError,
    )

    expect(setError).toHaveBeenCalledWith('supplier_id', { message: 'not a supplier' })
    expect(setError).toHaveBeenCalledWith('company_site_id', { message: 'wrong company' })
    expect(setError).toHaveBeenCalledWith('copies', { message: 'too many' })
    expect(setError).toHaveBeenCalledWith('lines.1.cost_type_id', { message: 'inactive' })
    expect(setError).toHaveBeenCalledWith('lines.0.quantity', { message: 'gt 0' })
    expect(unmapped).toEqual(['another order', 'at least one'])
  })

  it('returns null for anything but a 422', () => {
    const conflict = new AxiosError('conflict', '409', undefined, undefined, { status: 409, data: {} } as AxiosResponse)
    expect(applyOrderServerErrors(conflict, vi.fn())).toBeNull()
    expect(applyOrderServerErrors(new Error('boom'), vi.fn())).toBeNull()
  })
})
