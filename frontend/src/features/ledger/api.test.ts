import { describe, expect, it } from 'vitest'
import { defaultLedgerFilters, ledgerKeys, toLedgerParams } from '@/features/ledger/api'

describe('ledger api helpers', () => {
  it('AC-017 defaults to invoices from 1 January of the current year to today', () => {
    const filters = defaultLedgerFilters()
    const year = String(new Date().getFullYear())

    expect(filters.documentType).toBe('invoice')
    expect(filters.dateFrom).toBe(`${year}-01-01`)
    expect(filters.dateTo.startsWith(year)).toBe(true)
  })

  it('AC-017 maps filters to contract parameters and omits empty ones', () => {
    const params = toLedgerParams({
      documentType: 'all',
      dateFrom: '',
      dateTo: '2026-06-30',
      companyIds: [1, 2],
      companySiteIds: [],
      operationalSiteIds: [9],
    })

    expect(params).toEqual({
      document_type: 'all',
      date_to: '2026-06-30',
      company_id: [1, 2],
      operational_site_id: [9],
    })
  })

  it('AC-017 puts the filters in the query key', () => {
    const base = { document_type: 'invoice' as const }
    expect(ledgerKeys.detail(7, base)).not.toEqual(ledgerKeys.detail(7, { ...base, company_id: [1] }))
  })
})
