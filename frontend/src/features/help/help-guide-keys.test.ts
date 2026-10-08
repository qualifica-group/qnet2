import { describe, expect, it } from 'vitest'
import { GENERAL_HELP_KEY, HELP_GUIDE_KEYS } from '@/features/help/help-guide-keys'

describe('HELP_GUIDE_KEYS (spec 0143 context: 49 navigation keys + general; spec 0150 adds "notifications"; spec 0175 adds "email-templates"/"document-bundles"; spec 0185 adds "request-statistics"; spec 0187 adds "system-health"; spec 0189 adds "financial-accounts"; spec 0193 adds "proforma-requests"; spec 0194 adds "invoices"; spec 0197 adds "invoice-installments"; spec 0201 adds "work-order-payment-statuses")', () => {
  it('has exactly 60 unique keys, "general" first', () => {
    expect(HELP_GUIDE_KEYS).toHaveLength(60)
    expect(new Set(HELP_GUIDE_KEYS).size).toBe(60)
    expect(HELP_GUIDE_KEYS[0]).toBe(GENERAL_HELP_KEY)
  })
})
