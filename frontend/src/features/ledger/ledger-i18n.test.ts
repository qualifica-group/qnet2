import { describe, expect, it } from 'vitest'
import { invoiceInstallments as itActive } from '@/i18n/locales/it-invoice-installments'
import { invoiceInstallments as enActive } from '@/i18n/locales/en-invoice-installments'
import { purchaseInvoiceInstallments as itPassive } from '@/i18n/locales/it-purchase-invoice-installments'
import { purchaseInvoiceInstallments as enPassive } from '@/i18n/locales/en-purchase-invoice-installments'
import { enums as itEnums } from '@/i18n/locales/it-enums'
import { enums as enEnums } from '@/i18n/locales/en-enums'

describe('spec 0361 write-off labels', () => {
  it('has the grid column labels in IT and EN on both grids', () => {
    for (const columns of [itActive.columns, enActive.columns, itPassive.columns, enPassive.columns]) {
      expect(columns.write_off_amount).toBeTruthy()
      expect(columns.write_off_reason).toBeTruthy()
    }
  })

  it('has the write_off_reason set filter option labels in IT and EN', () => {
    for (const enums of [itEnums, enEnums]) {
      expect(Object.keys(enums.write_off_reason).sort()).toEqual(['bad_debt', 'rounding'])
    }
  })
})
